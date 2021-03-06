<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Neucore\Service\EntityManager;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\WriteErrorListener;

class EntityManagerTest extends TestCase
{
    /**
     * @var WriteErrorListener
     */
    private static $writeErrorListener;

    /**
     * @var EntityManagerInterface
     */
    private static $em;

    public static function setupBeforeClass(): void
    {
        self::$writeErrorListener = new WriteErrorListener();
        self::$em = (new Helper())->getEm();
    }

    public function tearDown(): void
    {
        self::$em->getEventManager()->removeEventListener(Events::onFlush, self::$writeErrorListener);
    }

    public function testFlush()
    {
        $em = new EntityManager(self::$em, new Logger('Test'));

        $this->assertTrue($em->flush());
    }

    public function testFlushException()
    {
        self::$em->getEventManager()->addEventListener(Events::onFlush, self::$writeErrorListener);

        $log = new Logger('Test');

        $om = new EntityManager(self::$em, $log);

        $this->assertFalse($om->flush());
        $this->assertSame('error', $log->getHandler()->getRecords()[0]['message']);
    }
}
