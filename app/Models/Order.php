<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'client_id',
        'received_date',
        'received_time',
        'delivery_date',
        'delivery_time',
        'status',
        'total',
        'paid',
        'balance',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {

            if (!empty($order->number))
                return;

            DB::transaction(function () use ($order) {
                $last = DB::table('orders')->lockForUpdate()->max('id');
                $next = ($last ?? 0) + 1;

                $order->number = str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            });
        });

        static::saving(function (Order $order) {
            $order->balance = max(0, (float) $order->total - (float) $order->paid);
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
