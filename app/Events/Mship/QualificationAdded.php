<?php

namespace App\Events\Mship;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use App\Models\Mship\Account;
use App\Models\Mship\Qualification;

class QualificationAdded extends Event
{
    use SerializesModels;

    public $account;
    public $qualification;

    public function __construct(Account $account, Qualification $qualification)
    {
        $this->account = $account;
        $this->qualification = $qualification;
    }
}
