<?php

namespace App\Traits;

use App\Models\Client\AccountInfo;
use App\Models\Client\ClientAccount;

trait IFRS9
{
    public function finalLGD($account)
    {
        $this->ead($account);
        $this->lgd($account);
        $account->final_lgd = $account->ead ? $account->lgd / $account->ead : 1;
        return $account;
    }

    public function ead(AccountInfo $account)
    {
        $outstanding        = $account->outstanding_lcy ?: 0;
        $accruedInterest    = $account->accrued_interest_lcy ?: 0;
        $accruedInterestLcy = $account->accrued_interest_lcy ?: 0;
        $suspendedLcy       = $account->suspended_lcy ?: 0;

        $account->ead = $outstanding + $accruedInterest - $accruedInterestLcy - $suspendedLcy;
        return $account;
    }

    public function lgd($account)
    {
        $account      = $this->lgdUn($account);
        $account      = $this->lgdRe($account);
        $account      = $this->lgdSec($account);
        $account      = $this->lgdCm($account);
        $account->lgd = $account->lgd_cm + $account->lgd_sec + $account->lgd_un + $account->lgd;
        return $account;
    }

    public function lgdUn($account)
    {
        $account = $this->uncovered($account);
//       X5*Classification-IFRS9
        $account->lgd_un = $account->uncovered;
        return $account;
    }

    public function uncovered($account)
    {
        $account           = $this->coveredByRe($account);
        $account->uncovered = $account->ead
                             - $account->covered_by_cm
                             - $account->covered_by_sec
                             - $account->covered_by_re;
        return $account;
    }

    public function coveredByRe($account)
    {
        $account = $this->coveredBySec($account);
        $account = $this->allocationOfReGuarantee($account);

        if ($this->clientAccount($account)->main_currency_id == $this->clientAccount($account)->guarntee_currency_id) {
            $account->covered_by_re = min($account->ead
                                          - $account->covered_by_cm
                                          - $account->covered_by_sec,
                                          $account->pv_guarantee_amount_by_re
                                          *
                                          $account->allocation_re_guarantee

            );
        } else {
            $account->covered_by_re = min($account->ead
                                          - $account->covered_by_cm
                                          - $account->covered_by_sec,
                                          $account->pv_guarantee_amount_by_re *
                                          $account->allocation_re_guarantee * 0.9);
        }

        return $account;
    }

    public function coveredBySec($account)
    {
        $account = $this->coveredByCm($account);
        $account = $this->allocationOfSecGuarantee($account);

        if ($this->clientAccount($account)->main_currency_id == $this->clientAccount($account)->guarntee_currency_id) {
            $account->covered_by_sec = min($account->ead
                                           - $account->covered_by_cm,
                                           $account->pv_securities_guarantees
                                           *
                                           $account->allocation_sec_guarantee

            );
        } else {
            $account->covered_by_sec = min($account->ead
                                          - $account->covered_by_cm,
                                          $account->pv_securities_guarantees *
                                          $account->allocation_sec_guarantee * 0.9);
        }
        return $account;

    }

    public function coveredByCm($account)
    {
        $account = $this->allocationOfCmGuarantee($account);

        if ($this->clientAccount($account)->main_currency_id == $this->clientAccount($account)->guarntee_currency_id) {
            $account->covered_by_cm = min($account->ead,
                                           $account->cm_guarantee
                                           *
                                           $account->allocation_cm_guarantee

            );
        } else {
            $account->covered_by_cm = min($account->ead,
                                          $account->cm_guarantee *
                                          $account->allocation_cm_guarantee * 0.9);
        }

        return $account;
    }

    public function allocationOfCmGuarantee(AccountInfo $account): AccountInfo
    {
        if ($account->cm_guarantee != 0) {
            $sum = $this->getEadSum($account);

            if (isset($account->cm_guarantee) and $account->cm_guarantee > $sum) {
                $account->allocation_cm_guarantee = 1;
            } else {
                $account->allocation_cm_guarantee = $account->ead / $sum;
            }
        } else {
            $account->allocation_cm_guarantee = 0;
        }
        return $account;
    }

    private function getEadSum($account)
    {
        $temp = AccountInfo::join('client_accounts', 'client_accounts.id', '=', 'account_infos.client_account_id')
                           ->join('clients', 'clients.id', 'client_accounts.client_id')
                           ->where('cif', $this->clientAccount($account)->client->cif)
                           ->get();

        $sum = 0;
        foreach ($temp as $item) {
            $item = $this->ead($item);
            $sum  += $item->ead;
        }
        return $sum;
    }

    private function clientAccount($info)
    {
        return ClientAccount::where('id', $info->client_account_id)->first();
    }

    public function allocationOfSecGuarantee(AccountInfo $account): AccountInfo
    {
        if ($account->pv_securities_guarantees != 0) {
            $sum = $this->getEadSum($account);

            if (isset($account->pv_securities_guarantees) and $account->pv_securities_guarantees > $sum) {
                $account->allocation_sec_guarantee = 1;
            } else {
                $account->allocation_sec_guarantee = $account->ead / $sum;
            }
        } else {
            $account->allocation_sec_guarantee = 0;
        }
        return $account;
    }

    public function allocationOfReGuarantee(AccountInfo $account): AccountInfo
    {
        if ($account->pv_re_guarantees != 0) {
            $sum = $this->getEadSum($account);

            if (isset($account->pv_re_guarantees) and $account->pv_re_guarantees > $sum) {
                $account->allocation_re_guarantee = 1;
            } else {
                $account->allocation_re_guarantee = $account->ead / $sum;
            }
        } else {
            $account->allocation_re_guarantee = 0;
        }
        return $account;
    }

    public function lgdRe($account)
    {
        $account = $this->coveredByRe($account);
//       X5*Classification-IFRS9
        $account->lgd_re = $account->covered_by_re;
        return $account;
    }

    public function lgdSec($account)
    {
//       X5*Classification-IFRS9
        $account->lgd_sec = $account->covered_by_sec;
        return $account;
    }

    public function lgdCm($account)
    {
//       X5*Classification-IFRS9
        $account->lgd_cm = $account->covered_by_cm;
        return $account;
    }


}
