<?php

namespace Tests\Unit\Usage;

use Appwrite\Usage\Context;
use PHPUnit\Framework\TestCase;

class ContextTest extends TestCase
{
    public function testDatabaseMetricsRetainTheirResourceAfterProjectFallback(): void
    {
        $usage = new Context();
        $usage
            ->setResource('database')
            ->setResourceId('database-a')
            ->setResourceInternalId('10')
            ->addMetric('databases.operations.writes', 2)
            ->setResourceId('database-b')
            ->setResourceInternalId('20')
            ->addMetric('databases.operations.writes', 3);

        $usage
            ->fillMissingResource('project', 'project-id', '1')
            ->addMetric('network.requests', 1);

        $metrics = $usage->getMetrics();
        $this->assertSame(['database', 'database', 'project'], array_column($metrics, 'resourceType'));
        $this->assertSame(['database-a', 'database-b', 'project-id'], array_column($metrics, 'resourceId'));
        $this->assertSame(['10', '20', '1'], array_column($metrics, 'resourceInternalId'));
        $this->assertSame([2, 3, 1], array_column($metrics, 'value'));
    }

    public function testProjectFallbackFillsUnattributedMetrics(): void
    {
        $usage = new Context();
        $usage->addMetric('network.requests', 1);

        $usage->fillMissingResource('project', 'project-id', '1');

        $metric = $usage->getMetrics()[0];
        $this->assertSame('project', $metric['resourceType']);
        $this->assertSame('project-id', $metric['resourceId']);
        $this->assertSame('1', $metric['resourceInternalId']);
        $this->assertSame(1, $metric['value']);
    }
}
