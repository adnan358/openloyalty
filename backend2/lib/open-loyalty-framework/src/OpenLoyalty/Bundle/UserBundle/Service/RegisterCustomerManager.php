<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\UserBundle\Service;

use Broadway\CommandHandling\CommandBus;
use Broadway\ReadModel\Repository;
use Doctrine\ORM\EntityManager;
use OpenLoyalty\Bundle\UserBundle\Entity\Customer;
use OpenLoyalty\Bundle\UserBundle\Entity\Status;
use OpenLoyalty\Bundle\UserBundle\Entity\User;
use OpenLoyalty\Component\Customer\Domain\Command\ActivateCustomer;
use OpenLoyalty\Component\Customer\Domain\Command\MoveCustomerToLevel;
use OpenLoyalty\Component\Customer\Domain\Command\NewsletterSubscription;
use OpenLoyalty\Component\Customer\Domain\Command\RegisterCustomer;
use OpenLoyalty\Component\Customer\Domain\Command\UpdateCustomerAddress;
use OpenLoyalty\Component\Customer\Domain\Command\UpdateCustomerCompanyDetails;
use OpenLoyalty\Component\Customer\Domain\Command\UpdateCustomerLoyaltyCardNumber;
use OpenLoyalty\Component\Customer\Domain\CustomerId;
use OpenLoyalty\Component\Customer\Domain\Exception\EmailAlreadyExistsException;
use OpenLoyalty\Component\Customer\Domain\Exception\LoyaltyCardNumberAlreadyExistsException;
use OpenLoyalty\Component\Customer\Domain\Exception\PhoneAlreadyExistsException;
use OpenLoyalty\Component\Customer\Domain\LevelId;
use OpenLoyalty\Component\Customer\Domain\Validator\CustomerUniqueValidator;

/**
 * Class RegisterCustomerManager.
 */
class RegisterCustomerManager
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var CustomerUniqueValidator
     */
    protected $customerUniqueValidator;

    /**
     * @var CommandBus
     */
    protected $commandBus;

    /**
     * @var Repository
     */
    protected $customerRepository;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * RegisterCustomerManager constructor.
     *
     * @param UserManager             $userManager
     * @param CustomerUniqueValidator $customerUniqueValidator
     * @param CommandBus              $commandBus
     * @param Repository              $customerRepository
     * @param EntityManager           $entityManager
     */
    public function __construct(UserManager $userManager, CustomerUniqueValidator $customerUniqueValidator, CommandBus $commandBus, Repository $customerRepository, EntityManager $entityManager)
    {
        $this->userManager = $userManager;
        $this->customerUniqueValidator = $customerUniqueValidator;
        $this->commandBus = $commandBus;
        $this->customerRepository = $customerRepository;
        $this->entityManager = $entityManager;
    }

    /**
     * @param CustomerId  $customerId
     * @param array       $customerData
     * @param null|string $plainPassword
     *
     * @throws EmailAlreadyExistsException
     * @throws LoyaltyCardNumberAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     *
     * @return Customer
     */
    public function register(CustomerId $customerId, array $customerData, ? string $plainPassword = null): Customer
    {
        if (!isset($customerData['email'])) {
            throw new \InvalidArgumentException('email key does not exist in customerData');
        }
        $email = $customerData['email'];

        if ($email) {
            if ($this->userManager->isCustomerExist($email)) {
                throw new EmailAlreadyExistsException('This email is already taken');
            }
            $this->customerUniqueValidator->validateEmailUnique($email, $customerId);
        }

        if (isset($customerData['loyaltyCardNumber'])) {
            $this->customerUniqueValidator->validateLoyaltyCardNumberUnique(
                $customerData['loyaltyCardNumber'],
                $customerId
            );
        }
        if (isset($customerData['phone']) && $customerData['phone']) {
            $this->customerUniqueValidator->validatePhoneUnique($customerData['phone']);
        }

        $command = new RegisterCustomer($customerId, $customerData);
        $this->commandBus->dispatch($command);

        if (isset($customerData['address'])) {
            $updateAddressCommand = new UpdateCustomerAddress($customerId, $customerData['address']);
            $this->commandBus->dispatch($updateAddressCommand);
        }
        if (isset($customerData['company']) && $customerData['company'] && $customerData['company']['name']
            && $customerData['company']['nip']) {
            $updateCompanyDataCommand = new UpdateCustomerCompanyDetails($customerId, $customerData['company']);
            $this->commandBus->dispatch($updateCompanyDataCommand);
        }
        if (isset($customerData['loyaltyCardNumber'])) {
            $loyaltyCardCommand = new UpdateCustomerLoyaltyCardNumber($customerId, $customerData['loyaltyCardNumber']);
            $this->commandBus->dispatch($loyaltyCardCommand);
        }

        if (isset($customerData['level'])) {
            $this->commandBus->dispatch(
                new MoveCustomerToLevel($customerId, new LevelId($customerData['level']), true)
            );
        }

        return $this->userManager->createNewCustomer(
            $customerId,
            $email,
            $plainPassword,
            isset($customerData['phone']) ? $customerData['phone'] : null
        );
    }

    /**
     * @param Customer $user
     */
    public function activate(Customer $user): void
    {
        $user->setIsActive(true);
        $user->setStatus(Status::typeActiveNoCard());

        $this->commandBus->dispatch(
            new ActivateCustomer(new CustomerId($user->getId()))
        );

        $this->userManager->updateUser($user);

        $customerId = new CustomerId($user->getId());
        $customer = $this->customerRepository->find($user->getId());

        if ($customer->isAgreement2()) {
            $this->dispatchNewsletterSubscriptionEvent($user, $customerId);
        }
    }

    /**
     * @param User       $user
     * @param CustomerId $customerId
     */
    public function dispatchNewsletterSubscriptionEvent(User $user, CustomerId $customerId): void
    {
        if (!$user->getNewsletterUsedFlag()) {
            $user->setNewsletterUsedFlag(true);
            $this->entityManager->flush();

            $this->commandBus->dispatch(
                new NewsletterSubscription($customerId)
            );
        }
    }
}
