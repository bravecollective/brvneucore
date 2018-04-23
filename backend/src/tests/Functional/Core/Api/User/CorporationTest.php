<?php declare(strict_types=1);

namespace Tests\Functional\Core\Api\User;

use Brave\Core\Entity\AllianceRepository;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationRepository;
use Brave\Core\Entity\Group;
use Brave\Core\Roles;
use Brave\Core\Service\EveTokenService;
use Brave\Core\Service\EsiService;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class CorporationTest extends WebTestCase
{
    private $h;

    private $em;

    private $cid1;

    private $cid2;

    private $gid1;

    private $gid2;

    private $corpRepo;

    private $alliRepo;

    private $alliApi;

    private $corpApi;

    private $esi;

    public function setUp()
    {
        $_SESSION = null;

        $this->h = new Helper();
        $this->em = $this->h->getEm();

        $this->corpRepo = new CorporationRepository($this->em);
        $this->alliRepo = new AllianceRepository($this->em);

        // mock Swagger API
        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());
        $oauth = $this->createMock(GenericProvider::class);
        $ts = new EveTokenService($oauth, $this->em, $log);
        $this->alliApi = $this->createMock(AllianceApi::class);
        $this->corpApi = $this->createMock(CorporationApi::class);
        $this->esi = new EsiService($log, $ts, $this->alliApi, $this->corpApi);
    }

    public function testAll403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAll200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/all');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
                ['id' => 111, 'name' => 'corp 1', 'ticker' => 't1', 'alliance' => null],
                ['id' => 222, 'name' => 'corp 2', 'ticker' => 't2', 'alliance' => null],
                ['id' => 333, 'name' => 'corp 3', 'ticker' => 't3', 'alliance' => null]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testWithGroups403()
    {
        $this->setupDb();

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testWithGroups200()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('GET', '/api/user/corporation/with-groups');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([
                ['id' => 111, 'name' => 'corp 1', 'ticker' => 't1', 'groups' => [
                    ['id' => $this->gid1, 'name' => 'group 1', 'public' => false]
                ]],
                ['id' => 222, 'name' => 'corp 2', 'ticker' => 't2', 'groups' => [
                    ['id' => $this->gid1, 'name' => 'group 1', 'public' => false],
                    ['id' => $this->gid2, 'name' => 'group 2', 'public' => false]
                ]]
            ],
            $this->parseJsonBody($response)
        );
    }

    public function testAdd403()
    {
        $this->setupDb();

        $response = $this->runApp('POST', '/api/user/corporation/add/123');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('POST', '/api/user/corporation/add/123');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAdd400()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiService class
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception("failed to coerce value '123456789123' into type integer", 400))
        );

        $response = $this->runApp('POST', '/api/user/corporation/add/123456789123', null, null, [
            EsiService::class => $this->esi
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testAdd404()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiService class
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception("#", 404))
        );

        $response = $this->runApp('POST', '/api/user/corporation/add/123', null, null, [
            EsiService::class => $this->esi
        ]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testAdd409()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response = $this->runApp('POST', '/api/user/corporation/add/111');
        $this->assertEquals(409, $response->getStatusCode());
    }

    public function testAdd503()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiService class
        $this->corpApi->method('getCorporationsCorporationId')->will(
            $this->throwException(new \Exception("Oops.", 503))
        );

        $response = $this->runApp('POST', '/api/user/corporation/add/123', null, null, [
            EsiService::class => $this->esi
        ]);

        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testAdd201WithoutAlliance()
    {
        $this->setupDb();
        $this->loginUser(7);

        // method is called via EsiService class
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.',
            'ticker' => '-CT-',
            'alliance_id' => null
        ]));

        $response = $this->runApp('POST', '/api/user/corporation/add/456123', null, null, [
            EsiService::class => $this->esi
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 456123, 'name' => 'The Corp.', 'ticker' => '-CT-', 'alliance' => null],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        // check db
        $corp = $this->corpRepo->find(456123);
        $this->assertNull($corp->getAlliance());
        $this->assertSame(456123, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-CT-', $corp->getTicker());
    }

    public function testAdd201WitAlliance()
    {
        $this->setupDb();
        $this->loginUser(7);

        // methods are called via EsiService class
        $this->corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.',
            'ticker' => '-CT-',
            'alliance_id' => 123456
        ]));
        $this->alliApi->method('getAlliancesAllianceId')->willReturn(new GetAlliancesAllianceIdOk([
            'name' => 'The Alliance.',
            'ticker' => '-AT-',
        ]));

        $response = $this->runApp('POST', '/api/user/corporation/add/456123', null, null, [
            EsiService::class => $this->esi
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertSame(
            ['id' => 456123, 'name' => 'The Corp.', 'ticker' => '-CT-', 'alliance' => [
                'id' => 123456, 'name' => 'The Alliance.', 'ticker' => '-AT-'
            ]],
            $this->parseJsonBody($response)
        );

        $this->em->clear();

        // check that corp and aliance were created in db
        $corp = $this->corpRepo->find(456123);
        $alli = $this->alliRepo->find(123456);

        $this->assertSame($alli->getId(), $corp->getAlliance()->getId());

        $this->assertSame(456123, $corp->getId());
        $this->assertSame('The Corp.', $corp->getName());
        $this->assertSame('-CT-', $corp->getTicker());

        $this->assertSame(123456, $alli->getId());
        $this->assertSame('The Alliance.', $alli->getName());
        $this->assertSame('-AT-', $alli->getTicker());
    }

    public function testAddGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testAddGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/add-group/5');
        $response2 = $this->runApp('PUT', '/api/user/corporation/123/add-group/'.$this->gid2);
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/add-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testAddGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/add-group/'.$this->gid2);
        $response2 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/add-group/'.$this->gid2);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    public function testRemoveGroup403()
    {
        $this->setupDb();

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());

        $this->loginUser(6); # not a user-admin

        $response = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testRemoveGroup404()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/remove-group/5');
        $response2 = $this->runApp('PUT', '/api/user/corporation/123/remove-group/'.$this->gid1);
        $response3 = $this->runApp('PUT', '/api/user/corporation/123/remove-group/5');
        $this->assertEquals(404, $response1->getStatusCode());
        $this->assertEquals(404, $response2->getStatusCode());
        $this->assertEquals(404, $response3->getStatusCode());
    }

    public function testRemoveGroup204()
    {
        $this->setupDb();
        $this->loginUser(7);

        $response1 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/remove-group/'.$this->gid1);
        $response2 = $this->runApp('PUT', '/api/user/corporation/'.$this->cid1.'/remove-group/'.$this->gid1);
        $this->assertEquals(204, $response1->getStatusCode());
        $this->assertEquals(204, $response2->getStatusCode());
    }

    private function setupDb()
    {
        $this->h->emptyDb();

        $this->h->addCharacterMain('User', 6, [Roles::USER]);
        $this->h->addCharacterMain('Admin', 7, [Roles::USER, Roles::USER_ADMIN]);

        $corp1 = (new Corporation())->setId(111)->setTicker('t1')->setName('corp 1');
        $corp2 = (new Corporation())->setId(222)->setTicker('t2')->setName('corp 2');
        $corp3 = (new Corporation())->setId(333)->setTicker('t3')->setName('corp 3');

        $group1 = (new Group())->setName('group 1');
        $group2 = (new Group())->setName('group 2');

        $corp1->addGroup($group1);
        $corp2->addGroup($group1);
        $corp2->addGroup($group2);

        $this->em->persist($corp1);
        $this->em->persist($corp2);
        $this->em->persist($corp3);
        $this->em->persist($group1);
        $this->em->persist($group2);

        $this->em->flush();

        $this->cid1 = $corp1->getId();
        $this->cid2 = $corp2->getId();

        $this->gid1 = $group1->getId();
        $this->gid2 = $group2->getId();
    }
}
