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
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire(env: 'file:resolve:APP_DMARC_REPORT_XSD_PATH')]
        private string $schemaSource,
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
                    'http://dmarc.org/dmarc-xml/0.1',
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

            // 3. Schema validation
            if (!$dom->schemaValidateSource($this->schemaSource)) {
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

            // 4. Validate source_ip values (IPv4 / IPv6)
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

            return $serializer->deserialize(
                data: $xmlContent,
                type: Feedback::class,
                format: 'xml',
            );
        } finally {
            unset($entityLoaderSet);
            libxml_set_external_entity_loader($prevEntityLoader);
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
