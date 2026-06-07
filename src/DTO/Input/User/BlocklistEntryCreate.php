<?php

declare(strict_types=1);

namespace App\DTO\Input\User;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final readonly class BlocklistEntryCreate
{
    private const string LOCAL_PART_REGEX = '/^(?=.{1,64}$)(?:\*|[A-Za-z0-9*](?:[A-Za-z0-9*_%+-]{0,62}[A-Za-z0-9*])?)(?:\.(?:\*|[A-Za-z0-9*](?:[A-Za-z0-9*_%+-]{0,62}[A-Za-z0-9*])?))*$/';
    private const string DOMAIN_PART_REGEX = '/^(?=.{1,253}$)(?:[A-Za-z0-9*](?:[A-Za-z0-9*-]{0,61}[A-Za-z0-9*])?\.)+(?:\*|[A-Za-z0-9*][A-Za-z0-9*-]{0,61}[A-Za-z0-9*])$/';
    private const string UNIVERSAL_DOMAIN_PATTERN_REGEX = '/^[*.]+$/';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[Assert\Callback([self::class, 'validatePattern'])]
        public ?string $pattern = null,
    ) {}

    public static function validatePattern(mixed $value, ExecutionContextInterface $context, mixed $payload): void
    {
        if (null === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $pattern = trim($value);
        if ('' === $pattern) {
            $context->buildViolation('Pattern must not be empty.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        if (1 === preg_match('/\s/', $pattern)) {
            $context->buildViolation('Pattern must not contain whitespace.')
                ->atPath('pattern')
                ->addViolation()
            ;
        }

        if (str_contains($pattern, ',')) {
            $context->buildViolation('Pattern must not contain a comma.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        if (1 !== substr_count($pattern, '@')) {
            $context->buildViolation('Pattern must contain exactly one "@" character.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        [$local, $domain] = explode('@', $pattern, 2);
        if ('' === $local || '' === $domain) {
            $context->buildViolation('Pattern must contain non-empty local and domain parts.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        if (1 !== preg_match(self::LOCAL_PART_REGEX, $local)) {
            $context->buildViolation('Pattern local part is invalid.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        if (1 !== preg_match(self::DOMAIN_PART_REGEX, $domain)) {
            $context->buildViolation('Pattern domain part is invalid.')
                ->atPath('pattern')
                ->addViolation()
            ;

            return;
        }

        if (self::isUniversalPattern($local, $domain)) {
            $context->buildViolation('Pattern must not match all email addresses.')
                ->atPath('pattern')
                ->addViolation()
            ;
        }

        if (1 !== preg_match('/^[\x21-\x7E]+$/', $pattern)) {
            $context->buildViolation('Pattern must contain only ASCII printable characters.')
                ->atPath('pattern')
                ->addViolation()
            ;
        }
    }

    private static function isUniversalPattern(string $local, string $domain): bool
    {
        return 1 === preg_match('/^\*+$/', $local)
            && 1 === preg_match(self::UNIVERSAL_DOMAIN_PATTERN_REGEX, $domain);
    }
}
