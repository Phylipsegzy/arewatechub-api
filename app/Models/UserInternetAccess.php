<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserInternetAccess extends Model
{
    // Eloquent's automatic guess would be "user_internet_accesses" (it
    // pluralizes "access" to "accesses"), but the migration — matching your
    // legacy table name exactly — created it singular. Without this, every
    // query against this model fails with "table doesn't exist".
    protected $table = 'user_internet_access';

    protected $fillable = ['booking_id', 'customer_id', 'internet_account_id', 'start_date', 'end_date'];

    public function internetAccount()
    {
        return $this->belongsTo(InternetAccount::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
