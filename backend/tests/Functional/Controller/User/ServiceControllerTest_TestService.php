<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Plugin\CoreCharacter;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceControllerTest_TestService implements ServiceInterface
{
    public function __construct(LoggerInterface $logger)
    {
    }

    /**
     * @param CoreCharacter[] $characters
     * @param CoreGroup[] $groups
     * @return ServiceAccountData[]
     * @throws Exception
     */
    public function getAccounts(array $characters, array $groups): array
    {
        if (count($groups) === 2 && $groups[1]->name === 'G4') {
            throw new Exception();
        }
        return [
            new ServiceAccountData(1, 'u', 'p', 'e', ServiceAccountData::STATUS_ACTIVE),
            new ServiceAccountData(3),
        ];
    }

    /**
     * @param CoreGroup[] $groups
     * @param int[] $allCharacterIds
     * @return ServiceAccountData
     * @throws Exception
     */
    public function register(
        CoreCharacter $character,
        array $groups,
        string $emailAddress,
        array $allCharacterIds
    ): ServiceAccountData {
        if (count($groups) === 2 && $groups[1]->name === 'G3') {
            throw new Exception();
        } elseif (count($groups) === 2 && $groups[1]->name === 'G5') {
            throw new Exception('test');
        } else {
            return new ServiceAccountData($character->id, 'u2', 'p2', 'e2', ServiceAccountData::STATUS_ACTIVE);
        }
    }

    public function updateAccount(CoreCharacter $character, array $groups): void
    {
        if ($character->id === 3) {
            throw new Exception();
        }
    }

    public function resetPassword(int $characterId): string
    {
        if ($characterId === 3) {
            throw new Exception();
        } else {
            return 'new-pass';
        }
    }
}