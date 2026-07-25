<?php

namespace Unitpay\Api;

/**
 * Subscription operations: list project subscriptions, fetch one, close one.
 * Method names mirror the Unitpay API method names.
 */
final class SubscriptionService extends AbstractService
{
    /**
     * @param int|string $projectId
     * @param array<string, mixed> $options (e.g. 'all' => true)
     */
    public function listSubscriptions($projectId, array $options = []): object
    {
        return $this->request('listSubscriptions', array_merge(['projectId' => $projectId], $options));
    }

    /**
     * @param int|string $subscriptionId
     * @param array<string, mixed> $options
     */
    public function getSubscription($subscriptionId, array $options = []): object
    {
        return $this->request('getSubscription', array_merge(['subscriptionId' => $subscriptionId], $options));
    }

    /**
     * @param int|string $subscriptionId
     * @param array<string, mixed> $options
     */
    public function closeSubscription($subscriptionId, array $options = []): object
    {
        return $this->request('closeSubscription', array_merge(['subscriptionId' => $subscriptionId], $options));
    }
}
