<?php

declare(strict_types=1);

namespace App\Tests\Templates;

use App\DTO\Output\User\WeeklyOverview\WeeklyOverview;
use App\Tests\Api\ApiTestCase;
use App\Tests\Service\User\DMARC\WeeklyOverviewFixture;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Part\DataPart;
use Twig\Environment;

/**
 * Renders every email the way the mailer does, with strict variables, and
 * checks what all of them share: the inline logo, a plain-text part and the
 * footer naming the instance.
 *
 * @internal
 */
#[CoversNothing]
final class EmailTemplatesTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, list<string>}>
     */
    public static function emails(): iterable
    {
        $tokenLink = 'email=first%2Blast%40example.test&token=a%2Fb%2Bc';

        yield 'email verification' => ['user/email-verification', ['name' => 'Alex', 'tokenEmail' => 'first+last@example.test', 'token' => 'a/b+c', 'tokenLifetimeHours' => 24], ['Hi Alex,', 'https://example.test/auth/email-verify?'.$tokenLink, '24 hours']];
        yield 'password reset' => ['user/password-reset', ['name' => 'Alex', 'tokenEmail' => 'first+last@example.test', 'token' => 'a/b+c', 'tokenLifetimeHours' => 24], ['Hi Alex,', 'https://example.test/auth/password-reset?'.$tokenLink]];
        yield 'password changed' => ['user/password-change', ['name' => 'Alex'], ['was just changed', 'https://example.test/auth/forgot-password']];
        yield 'two-factor code' => ['user/two-factor-code', ['name' => 'Alex', 'code' => '482913'], ['482913']];
        yield 'two-factor enabled' => ['user/two-factor-enabled', ['name' => 'Alex'], ['authenticator app', 'https://example.test/profile']];
        yield 'two-factor disabled' => ['user/two-factor-disabled', ['name' => 'Alex'], ['no longer uses an authenticator app']];
        yield 'login notification' => ['user/login-notification', ['name' => 'Alex', 'ip' => '203.0.113.10', 'userAgent' => 'TestAgent/1.0', 'occurredAt' => new \DateTimeImmutable('2026-10-21 08:30:00 UTC')], ['203.0.113.10', 'TestAgent/1.0', '2026-10-21 08:30 UTC', 'https://example.test/notifications']];
        yield 'weekly overview needing attention' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::needingAttention(), 'recipient' => 'weekly@example.test'], ['NEEDS ATTENTION', '10,649', '1,187 messages failed DMARC', 'carried a valid DKIM signature for mailer.example', '2 addresses, most of them from 198.51.100.10', 'They came from 203.0.113.61', 'started reporting', 'https://example.test/domains/0192e0c4-0000-7000-8000-000000000001', 'compared with Oct 5–11', 'every Tuesday', 'weekly@example.test']];
        yield 'weekly overview without issues' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::healthy(), 'recipient' => 'weekly@example.test'], ['NO ISSUES THIS WEEK', 'nothing that failed it was delivered', 'https://example.test/dashboard']];
        yield 'weekly overview with receiver overrides' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::healthyWithOverrides(), 'recipient' => 'weekly@example.test'], ['NO ISSUES THIS WEEK', 'your policies are enforced', 'Receivers still delivered 12 that failed', 'Based on 57 aggregate reports, compared with Oct 5–11.']];
        yield 'weekly overview of a first week' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::firstWeek(), 'recipient' => 'weekly@example.test'], ['1 message that failed DMARC reached receivers, possibly from subdomains, which its policy leaves open (sp=none).', 'It came from 203.0.113.9.', 'An sp=quarantine policy stops this for subdomains.', 'no reports the week before', 'p=reject, sp=none', 'Based on 1 aggregate report from google.com,']];
        yield 'weekly overview with a blocked service and a quiet domain' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::blockedServiceAndQuietDomain(), 'recipient' => 'weekly@example.test'], ['50 messages failed DMARC and were still delivered, because its policy covers only 25% of mail.', 'Receivers quarantined them.', 'They came from 3 addresses, most of them from 203.0.113.7 (Hosting, Poland).', 'example.net sent no reports.', 'reports covered 500 messages from it', 'p=quarantine, pct=25']];
        yield 'account email without a name' => ['user/password-change', ['name' => null], ['Hi,']];
        yield 'weekly overview without reports' => ['user/dmarc/weekly-overview', ['overview' => WeeklyOverviewFixture::withoutReports(), 'recipient' => 'weekly@example.test'], ['NO REPORTS THIS WEEK', 'rua=mailto:postbox-token@aggregate-reports.example.test']];
    }

    /**
     * @param array<string, mixed> $context
     * @param list<string>         $expectedText
     */
    #[DataProvider('emails')]
    public function testEmailRendersInTheSharedLayout(string $template, array $context, array $expectedText): void
    {
        $email = new TemplatedEmail()
            ->to('alex@example.test')
            ->subject('Test subject')
            ->htmlTemplate('emails/'.$template.'.html.twig')
            ->textTemplate('emails/'.$template.'.txt.twig')
            ->context($context)
        ;

        self::render($email);

        $html = (string) $email->getHtmlBody();
        $text = (string) $email->getTextBody();

        $logo = $email->getAttachments();
        self::assertCount(1, $logo);
        self::assertInstanceOf(DataPart::class, $logo[0]);
        self::assertSame('logo.png', $logo[0]->getFilename());
        self::assertSame('inline', $logo[0]->getDisposition());
        self::assertStringContainsString('src="cid:'.$logo[0]->getContentId().'"', $html);

        self::assertStringContainsString('Sent by DMARCo at <a href="https://example.test"', $html);
        self::assertStringEndsWith("Sent by DMARCo at https://example.test\n", $text);

        foreach ($expectedText as $snippet) {
            self::assertStringContainsString($snippet, $text);
        }
    }

    public function testTemplatesUseNoDeprecatedSyntax(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        $deprecations = [];
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $templates = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/templates/emails', \FilesystemIterator::SKIP_DOTS));
            foreach ($templates as $file) {
                \assert($file instanceof \SplFileInfo);
                if (str_ends_with($file->getFilename(), '.twig')) {
                    $name = substr($file->getPathname(), \strlen(\dirname(__DIR__, 2).'/templates/'));
                    $twig->compileSource($twig->getLoader()->getSourceContext($name));
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $deprecations);
    }

    public function testHealthyWeekDoesNotClaimNothingFailingWasDelivered(): void
    {
        $email = new TemplatedEmail()
            ->to('alex@example.test')
            ->subject('Test subject')
            ->htmlTemplate('emails/user/dmarc/weekly-overview.html.twig')
            ->textTemplate('emails/user/dmarc/weekly-overview.txt.twig')
            ->context(['overview' => WeeklyOverviewFixture::healthyWithOverrides(), 'recipient' => 'weekly@example.test'])
        ;

        self::render($email);

        self::assertStringNotContainsString('nothing that failed', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('Nothing that failed', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('nothing that failed', (string) $email->getTextBody());
    }

    public function testWordsFollowTheData(): void
    {
        $firstWeek = self::renderWeekly(WeeklyOverviewFixture::firstWeek());
        // Each label once: "no reports the week before the week before" was a bug.
        self::assertSame(4, substr_count($firstWeek, 'no reports the week before'));
        self::assertStringNotContainsString('the week before the week before', $firstWeek);
        self::assertDoesNotMatchRegularExpression('/(?<![\d,])1 (messages|aggregate reports)/', $firstWeek);
        self::assertStringContainsString('1 message that failed', $firstWeek);

        // The service's mail was all quarantined, so the delivered failures
        // did not come from it.
        $blocked = self::renderWeekly(WeeklyOverviewFixture::blockedServiceAndQuietDomain());
        self::assertStringNotContainsString('Some of them came from a sending service', $blocked);
    }

    public function testWeeklyOverviewEscapesNamesFromWhois(): void
    {
        $email = new TemplatedEmail()
            ->to('alex@example.test')
            ->subject('Test subject')
            ->htmlTemplate('emails/user/dmarc/weekly-overview.html.twig')
            ->textTemplate('emails/user/dmarc/weekly-overview.txt.twig')
            ->context(['overview' => WeeklyOverviewFixture::needingAttention(), 'recipient' => 'weekly@example.test'])
        ;

        self::render($email);

        $html = (string) $email->getHtmlBody();
        self::assertStringContainsString('Mail &lt;Service&gt; Inc', $html);
        self::assertStringNotContainsString('Mail <Service> Inc', $html);
        self::assertStringContainsString('Hosting &amp; Co', $html);
        self::assertStringContainsString('Mail <Service> Inc', (string) $email->getTextBody());
    }

    private static function renderWeekly(WeeklyOverview $overview): string
    {
        $email = new TemplatedEmail()
            ->to('alex@example.test')
            ->subject('Test subject')
            ->htmlTemplate('emails/user/dmarc/weekly-overview.html.twig')
            ->textTemplate('emails/user/dmarc/weekly-overview.txt.twig')
            ->context(['overview' => $overview, 'recipient' => 'weekly@example.test'])
        ;

        self::render($email);

        return (string) $email->getTextBody();
    }

    private static function render(TemplatedEmail $email): void
    {
        self::bootKernel();
        $renderer = self::getContainer()->get('twig.mime_body_renderer');
        \assert($renderer instanceof BodyRenderer);
        $renderer->render($email);
    }
}
