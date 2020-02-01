<?php

namespace App\Import\User;

use App\Entity\User;
use App\Entity\UserType;
use App\Import\AbstractImporter;
use App\Import\FailedImportResult;
use App\Service\AttributePersister;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserImporter extends AbstractImporter {

    private $entityManager;

    private $attributePersister;

    public function __construct(AttributePersister $attributePersister, EntityManagerInterface $manager, SerializerInterface $serialiser, ValidatorInterface $validator, LoggerInterface $logger = null) {
        parent::__construct($serialiser, $validator, $logger);

        $this->attributePersister = $attributePersister;
        $this->entityManager = $manager;
    }

    public function import($json) {
        /** @var UserImportData $userImportData */
        $userImportData = $this->parseJson($json, UserImportData::class);

        /** @var UserType[] $types */
        $types = $this->entityManager->getRepository(UserType::class)
            ->findAll();

        $this->entityManager->beginTransaction();

        $added = [ ];
        $updated = [ ];

        try {
            foreach($userImportData->getUsers() as $userData) {
                $user = $this->entityManager->getRepository(User::class)
                    ->findOneBy([
                        'username' => $userData->getUsername()
                    ]);

                if($user === null) {
                    $user = (new User())
                        ->setUsername($userData->getUsername());
                }

                $user->setFirstname($userData->getFirstname());
                $user->setLastname($userData->getLastname());
                $user->setEmail($userData->getEmail());

                foreach($types as $type) {
                    if($type->getAlias() === $userData->getType()) {
                        $user->setType($type);
                    }
                }

                $this->attributePersister->persistUserAttributes($userData->getAttributes(), $user);
                $this->entityManager->persist($user);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            return new UserImportResult(true, $added, $updated);
        } catch(\Exception $e) {
            $this->entityManager->rollback();

            return new FailedImportResult($e);
        }
    }
}