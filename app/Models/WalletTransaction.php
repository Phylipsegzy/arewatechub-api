<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'customer_id', 'type', 'amount', 'balance_before', 'balance_after',
        'source', 'reference', 'status', 'proof_of_payment', 'description',
    ];

    protected $appends = ['proof_of_payment_url'];

    public function getProofOfPaymentUrlAttribute(): ?string
    {
        if (! $this->proof_of_payment) {
            return null;
        }

        // Built from the actual request host/port rather than config('app.url')
        // — a mismatched APP_URL in .env (very common on XAMPP, where it's
        // often left as the default "http://localhost" while the API is
        // actually served from "http://127.0.0.1:8000") produces a broken
        // image URL that silently 404s in the browser. Using the request's
        // own host means the URL always matches wherever the admin actually
        // reached the API from.
        $base = request()?->getSchemeAndHttpHost() ?? config('app.url');

        return rtrim($base, '/') . '/storage/' . ltrim($this->proof_of_payment, '/');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
