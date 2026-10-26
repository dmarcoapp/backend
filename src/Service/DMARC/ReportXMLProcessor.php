<?php

declare(strict_types=1);

namespace App\Service\DMARC;

use App\DTO\Input\DMARC\ReportXML\Feedback;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final readonly class ReportXMLProcessor
{
    private const string RFC7489_NAMESPACE = 'http://dmarc.org/dmarc-xml/0.1';
    private const string RFC9990_NAMESPACE = 'urn:ietf:params:xml:ns:dmarc-2.0';

    public function __construct(
        private LoggerInterface $logger,
        #[Autowire(env: 'file:resolve:APP_DMARC_REPORT_XSD_PATH')]
        private string $schemaSource,
        #[Autowire(env: 'file:resolve:APP_DMARC_RFC9990_REPORT_XSD_PATH')]
        private string $rfc9990SchemaSource,
    ) {}

    public function validate(string $xmlContent): bool
    {
        $prevInternalErrors = libxml_use_internal_errors(true);
        $prevEntityLoader = libxml_get_external_entity_loader();
        libxml_clear_errors();

        try {
            // Disable external entity loading (XSD + XXE)
            $entityLoaderSet = libxml_set_external_entity_loader(fn () => null);

            $dom = new \DOMDocument();
            $dom->resolveExternals = false;
            $dom->substituteEntities = false;
            $dom->recover = false;
            $dom->preserveWhiteSpace = false;

            if (false === $dom->loadXML($xmlContent, LIBXML_NONET | LIBXML_COMPACT)) {
                return false;
            }

            // 1. Namespace normalization.
            $root = $dom->documentElement;

            if (null === $root->namespaceURI) {
                $newRoot = $dom->createElementNS(
                    self::RFC7489_NAMESPACE,
                    $root->nodeName
                );

                while ($root->firstChild) {
                    $newRoot->appendChild($root->firstChild);
                }

                $dom->replaceChild($newRoot, $root);
            }

            // 2. Delete known invalid / vendor-specific elements.
            $xpath = new \DOMXPath($dom);

            $invalidElements = [
                // <feedback><version>
                '/*[local-name()="feedback"]/*[local-name()="version"]',

                // <policy_published><fo>
                '/*[local-name()="feedback"]/*[local-name()="policy_published"]/*[local-name()="fo"]',

                // <identifiers><envelope_from>
                '//*[local-name()="identifiers"]/*[local-name()="envelope_from"]',

                // <auth_results><spf><scope> (vendor extra)
                '//*[local-name()="spf"]/*[local-name()="scope"]',
            ];

            foreach ($invalidElements as $query) {
                $nodes = $xpath->query($query) ?: [];

                foreach ($nodes as $node) {
                    if (null !== $node->parentNode) {
                        $node->parentNode->removeChild($node);
                    }
                }
            }

            // 3. Put DKIM results before SPF results, as the schema expects.
            $this->moveDkimBeforeSpf($xpath);

            // RFC 9990 reports declare their own namespace; everything else
            // is checked against RFC 7489.
            $isRfc9990 = self::RFC9990_NAMESPACE === $dom->documentElement->namespaceURI;

            // 4. Absorb RFC 7489 habits that the RFC 9990 schema rejects.
            if ($isRfc9990) {
                $this->absorbRfc7489Habits($dom, $xpath);
            }

            // 5. Schema validation
            $schemaSource = $isRfc9990 ? $this->rfc9990SchemaSource : $this->schemaSource;

            if (!$dom->schemaValidateSource($schemaSource)) {
                $this->logger->error(
                    'XML schema validation failed.',
                    array_map(
                        fn (\LibXMLError $error) => trim($error->message),
                        libxml_get_errors()
                    )
                );

                libxml_clear_errors();

                return false;
            }

            // 6. Validate source_ip values (IPv4 / IPv6)
            if (!$this->validateSourceIps($dom)) {
                return false;
            }

            libxml_clear_errors();
            unset($entityLoaderSet);

            return true;
        } finally {
            libxml_use_internal_errors($prevInternalErrors);
            libxml_set_external_entity_loader($prevEntityLoader);
        }
    }

    /**
     * @throws ExceptionInterface
     */
    public function deserialize(string $xmlContent): Feedback
    {
        $prevEntityLoader = libxml_get_external_entity_loader();
        $entityLoaderSet = libxml_set_external_entity_loader(fn () => null);

        try {
            $phpDocExtractor = new PhpDocExtractor();
            $reflectionExtractor = new ReflectionExtractor();
            $propertyInfo = new PropertyInfoExtractor(
                typeExtractors: [$phpDocExtractor, $reflectionExtractor]
            );

            $normalizers = [
                new ArrayDenormalizer(),
                new ObjectNormalizer(propertyTypeExtractor: $propertyInfo),
            ];

            $encoders = [new XmlEncoder()];

            $serializer = new Serializer($normalizers, $encoders);

            return $serializer->denormalize(
                data: $this->withoutAttributes($serializer->decode($xmlContent, 'xml')),
                type: Feedback::class,
                format: 'xml',
            );
        } finally {
            unset($entityLoaderSet);
            libxml_set_external_entity_loader($prevEntityLoader);
        }
    }

    /**
     * The XML decoder keeps attributes as "@name" keys and puts the text of
     * an element with attributes under "#". The DTOs read no attributes, so
     * drop them. Otherwise an RFC 9990 <extra_contact_info lang="en"> would
     * decode to an array instead of a string.
     */
    private function withoutAttributes(mixed $data): mixed
    {
        if (!\is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key) || !str_starts_with($key, '@')) {
                $result[$key] = $this->withoutAttributes($value);
            }
        }

        return ['#'] === array_keys($result) ? $result['#'] : $result;
    }

    /**
     * Some reporters write <spf> before <dkim> inside <auth_results>. The
     * schema wants every <dkim> first, so move them ahead of the first <spf>
     * and keep their own order. Nothing else is touched, so an <auth_results>
     * without <spf> or with unknown elements still fails validation.
     */
    private function moveDkimBeforeSpf(\DOMXPath $xpath): void
    {
        $authResults = $xpath->query('//*[local-name()="auth_results"]') ?: [];

        foreach ($authResults as $authResult) {
            if (!$authResult instanceof \DOMElement) {
                continue;
            }

            $firstSpf = ($xpath->query('*[local-name()="spf"][1]', $authResult) ?: null)?->item(0);

            if (!$firstSpf instanceof \DOMElement) {
                continue;
            }

            $lateDkims = $xpath->query(
                '*[local-name()="spf"][1]/following-sibling::*[local-name()="dkim"]',
                $authResult
            ) ?: [];

            foreach ($lateDkims as $dkim) {
                if ($dkim instanceof \DOMElement) {
                    $authResult->insertBefore($dkim, $firstSpf);
                }
            }
        }
    }

    /**
     * Reporters that move to RFC 9990 may keep RFC 7489 habits: a <pct> in
     * <policy_published>, or a DKIM result without a <selector>. Drop the
     * <pct> and give such a DKIM result an empty <selector>, so the report
     * is not lost. Anything else, such as two <spf> results in one
     * <auth_results>, still fails validation.
     */
    private function absorbRfc7489Habits(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $pcts = $xpath->query(
            '/*[local-name()="feedback"]/*[local-name()="policy_published"]/*[local-name()="pct"]'
        ) ?: [];

        foreach ($pcts as $pct) {
            if ($pct instanceof \DOMElement) {
                $pct->parentNode?->removeChild($pct);
            }
        }

        $dkimsWithoutSelector = $xpath->query(
            '//*[local-name()="auth_results"]/*[local-name()="dkim"][not(*[local-name()="selector"])]'
        ) ?: [];

        foreach ($dkimsWithoutSelector as $dkim) {
            if ($dkim instanceof \DOMElement) {
                $dkim->appendChild($dom->createElementNS(self::RFC9990_NAMESPACE, 'selector'));
            }
        }
    }

    private function validateSourceIps(\DOMDocument $dom): bool
    {
        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query('//*[local-name()="source_ip"]') ?: [];

        foreach ($nodes as $node) {
            /**
             * @psalm-suppress UndefinedPropertyFetch
             */
            $ip = trim($node->textContent);

            if (
                false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            ) {
                $this->logger->error('Invalid source_ip value', [
                    'value' => $ip,
                ]);

                return false;
            }
        }

        return true;
    }
}
