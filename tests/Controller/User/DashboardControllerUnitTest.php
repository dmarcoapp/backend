<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Controller\User\DashboardController;
use App\DTO\Input\DMARC\DashboardSettings;
use App\DTO\Output\User\Dashboard;
use App\Entity\User\User;
use App\Repository\DMARC\DomainRepository;
use App\Repository\DMARC\ReportRepository;
use App\Service\User\DashboardMetricsProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[CoversClass(DashboardController::class)]
final class DashboardControllerUnitTest extends TestCase
{
    public function testGetThrowsWhenUserIdMissing(): void
    {
        $user = new User();
        $user
            ->setEmail('dashboard@example.com')
            ->setName('Dashboard User')
            ->setSharedPostboxIdentifierToken('dashboard-token')
        ;

        $controller = new DashboardController(
            $this->createStub(CacheInterface::class),
            (new \ReflectionClass(DashboardMetricsProvider::class))->newInstanceWithoutConstructor(),
        );

        $this->expectException(AccessDeniedException::class);
        $controller->get($user, new DashboardSettings(7));
    }

    public function testGetReturnsCachedDashboardResponse(): void
    {
        $user = new User();
        $user
            ->setEmail('dashboard-ok@example.com')
            ->setName('Dashboard User')
            ->setSharedPostboxIdentifierToken('dashboard-ok-token')
        ;
        $idProperty = new \ReflectionProperty($user, 'id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, Uuid::v4());

        $settings = new DashboardSettings(7);
        $dashboard = new Dashboard(
            reportCount: 1,
            reportTrend: 0.0,
            messageCount: 1,
            messageTrend: 0.0,
            passRate: 1.0,
            passTrend: 0.0,
            threatsBlockedCount: 0,
            threatsBlockedTrend: 0.0,
            messageCountByDate: [],
            threatCountByDate: [],
            compliance: [],
            complianceTrend: [],
            newDomainCount: 0,
            newDomainTrend: 0.0,
            newDomains: [],
            topOffenders: [],
            topSenders: [],
            sourceCountryDistribution: [],
            topReportingOrganizations: [],
        );

        $reportRepository = $this->createStub(ReportRepository::class);
        $reportRepository->method('getReportCountInDatePeriod')->willReturn(1);
        $reportRepository->method('getSumCountInDatePeriod')->willReturn(1);
        $reportRepository->method('getPassRate')->willReturn(1.0);
        $reportRepository->method('getThreatCount')->willReturn(0);
        $reportRepository->method('getMessageCountByDate')->willReturn([]);
        $reportRepository->method('getThreatCountByDate')->willReturn([]);
        $reportRepository
            ->method('getComplianceAverage')
            ->willReturn([['dmarc' => 0.0, 'dkim' => 0.0, 'spf' => 0.0]])
        ;
        $reportRepository->method('getOffendersInDatePeriod')->willReturn([]);
        $reportRepository->method('getTopSendersInDatePeriod')->willReturn([]);
        $reportRepository->method('getSourceCountryDistributionInDatePeriod')->willReturn([]);
        $reportRepository->method('getTopReportingOrganizationsInDatePeriod')->willReturn([]);

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('getNewDomainCount')->willReturn(0);
        $domainRepository->method('getNewDomains')->willReturn([]);

        $metrics = new DashboardMetricsProvider($reportRepository, $domainRepository);

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->expects(self::once())
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback): mixed {
                $item = new class implements ItemInterface {
                    public function getKey(): string
                    {
                        return 'key';
                    }

                    public function get(): mixed
                    {
                        return null;
                    }

                    public function isHit(): bool
                    {
                        return false;
                    }

                    public function set(mixed $value): static
                    {
                        return $this;
                    }

                    public function expiresAt(?\DateTimeInterface $expiration): static
                    {
                        return $this;
                    }

                    public function expiresAfter(\DateInterval|int|null $time): static
                    {
                        return $this;
                    }

                    public function tag(iterable|string $tags): static
                    {
                        return $this;
                    }

                    public function getMetadata(): array
                    {
                        return [];
                    }
                };

                $response = $callback($item);
                self::assertInstanceOf(JsonResponse::class, $response);

                return $response;
            })
        ;

        $controller = new DashboardController($cache, $metrics);
        $response = $controller->get($user, $settings);

        self::assertSame(200, $response->getStatusCode());
    }
}
