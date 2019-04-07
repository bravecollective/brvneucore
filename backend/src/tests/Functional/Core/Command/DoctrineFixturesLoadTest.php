<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class DoctrineFixturesLoadTest extends ConsoleTestCase
{
    public function testExecute()
    {
        // setup

        $helper = new Helper();
        $helper->emptyDb();
        $em = $helper->getEm();

        $em->persist((new Role(1))->setName(Role::USER));
        $em->persist((new Role(2))->setName(Role::APP));
        $em->persist((new Role(8))->setName(Role::ESI));
        $em->persist((new SystemVariable(SystemVariable::SHOW_PREVIEW_BANNER)));
        $em->persist((new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1'));
        $em->persist((new SystemVariable(SystemVariable::DIRECTOR_CHAR))->setScope(SystemVariable::SCOPE_SETTINGS));
        $em->flush();


        // run

        $output = explode("\n", $this->runConsoleApp('doctrine-fixtures-load'));

        $this->assertSame(3, count($output));
        $this->assertSame('loading Brave\Core\DataFixtures\RoleFixtureLoader', $output[0]);
        $this->assertSame('loading Brave\Core\DataFixtures\SystemVariablesFixtureLoader', $output[1]);
        $this->assertSame('', $output[2]);

        $repoFactory = new RepositoryFactory($em);
        $roles = $repoFactory->getRoleRepository()->findBy([]);
        $vars = $repoFactory->getSystemVariableRepository()->findBy([], ['name' => 'asc']);

        $this->assertSame(14, count($roles)); // 14 from seed
        $this->assertSame(12, count($vars)); // 11 from seed + 1 from setup

        // check that value was not changed
        $this->assertSame(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN, $vars[4]->getName());
        $this->assertSame('1', $vars[4]->getValue());
    }
}