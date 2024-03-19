<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Component\Account\Infrastructure\SystemEvent\Listener;

use OpenLoyalty\Component\Account\Domain\Command\AddPoints;
use OpenLoyalty\Component\Account\Domain\PointsTransferId;
use OpenLoyalty\Component\Account\Domain\ReadModel\AccountDetails;
use OpenLoyalty\Component\Account\Domain\TransactionId;
use OpenLoyalty\Component\EarningRule\Domain\ReferralEarningRule;
use OpenLoyalty\Component\Transaction\Domain\SystemEvent\CustomerAssignedToTransactionSystemEvent;

/**
 * Class ApplyEarningRuleToTransactionListener.
 */
class ApplyEarningRuleToTransactionListener extends BaseApplyEarningRuleListener
{
    /**
     * @param CustomerAssignedToTransactionSystemEvent $event
     */
    public function onRegisteredTransaction(CustomerAssignedToTransactionSystemEvent $event)
    {
        $customerId = $event->getCustomerId();
        $transactionId = $event->getTransactionId();
        $accounts = $this->accountDetailsRepository->findBy(['customerId' => $customerId->__toString()]);
        if (count($accounts) == 0 || $event->isReturn()) {
            return;
        }

        $pointsWithContext = $this->earningRuleApplier->evaluateTransactionWithComment(new TransactionId($transactionId->__toString()), $customerId->__toString());
        $points = $pointsWithContext['points'];
        $comment = $pointsWithContext['comment'];

        if ($points > 0) {
            /** @var AccountDetails $account */
            $account = reset($accounts);
            $this->commandBus->dispatch(
                new AddPoints(
                    $account->getAccountId(),
                    $this->pointsTransferManager->createAddPointsTransferInstance(
                        new PointsTransferId($this->uuidGenerator->generate()),
                        $points,
                        null,
                        false,
                        new TransactionId($transactionId->__toString()),
                        $comment
                    )
                )
            );
        }

        if (null !== $event->getTransactionsCount() && $event->getTransactionsCount() != 0) {
            $this->evaluateReferral(ReferralEarningRule::EVENT_EVERY_PURCHASE, $event->getCustomerId()->__toString());
        }
    }
}
