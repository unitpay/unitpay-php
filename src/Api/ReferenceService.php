<?php

namespace Unitpay\Api;

/**
 * Reference/account lookups: available payment methods, commissions, currency rates
 * and account balance. getCommissions/getCurrencyCourses/getPartner are account-level
 * (pass the account key in $options['secretKey'] and the account email as $login).
 * Method names mirror the Unitpay API method names.
 */
final class ReferenceService extends AbstractService
{
    /**
     * @param int|string $projectId
     * @param array<string, mixed> $options
     */
    public function getMethodsAvailable($projectId, array $options = []): object
    {
        return $this->request('getMethodsAvailable', array_merge(['projectId' => $projectId], $options));
    }

    /**
     * @param int|string $projectId
     * @param array<string, mixed> $options
     */
    public function getCommissions($projectId, string $login, array $options = []): object
    {
        return $this->request('getCommissions', array_merge([
            'projectId' => $projectId,
            'login'     => $login,
        ], $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getCurrencyCourses(string $login, array $options = []): object
    {
        return $this->request('getCurrencyCourses', array_merge(['login' => $login], $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function getPartner(string $login, array $options = []): object
    {
        return $this->request('getPartner', array_merge(['login' => $login], $options));
    }
}
