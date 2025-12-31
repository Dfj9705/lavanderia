<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'base_price',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relación: un servicio puede estar en muchos items de órdenes
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Accesor para mostrar la unidad bonita (opcional)
     */
    public function getUnitLabelAttribute(): string
    {
        return match ($this->unit) {
            'prenda' => 'Por prenda',
            'libra' => 'Por libra',
            default => 'Servicio fijo',
        };
    }
}
