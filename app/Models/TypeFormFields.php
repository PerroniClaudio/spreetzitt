<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeFormFields extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_type_id',
        'field_name',
        'field_type',
        'field_label',
        'required',
        'description',
        'placeholder',
        'default_value',
        'options',
        'validation',
        'validation_message',
        'help_text',
        'order',
        'hardware_limit',
        'include_no_type_hardware',
        'property_limit',
        'include_no_type_property',
        'property_types_list',
        'hardware_accessory_include',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            // Per il tipo hardware controlla che il valore di hardware_accessory_include sia valido
            if ($model->field_type == 'hardware') {
                $hardware_accessory_include_values = config('app.hardware_accessory_include_values');
                if(! $hardware_accessory_include_values) {
                    throw new \InvalidArgumentException(
                        'La configurazione per hardware_accessory_include_values non è definita.'
                    );
                }
                $allowed = array_keys($hardware_accessory_include_values ?? []);
                if (! in_array($model->hardware_accessory_include, $allowed, true)) {
                    throw new \InvalidArgumentException(
                        "Valore non valido per hardware_accessory_include: {$model->hardware_accessory_include}"
                    );
                }
            }
        });
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function hardwareTypes()
    {
        return $this->belongsToMany(HardwareType::class, 'type_form_field_hardware_type', 'type_form_field_id', 'hardware_type_id');
    }

    // Property types are stored as comma-separated values since they are fixed (1-5)
    public function getPropertyTypesAttribute()
    {
        if (! $this->property_types_list) {
            return [];
        }

        return explode(',', $this->property_types_list);
    }

    public function setPropertyTypesAttribute($value)
    {
        if (is_array($value)) {
            $this->attributes['property_types_list'] = implode(',', $value);
        } else {
            $this->attributes['property_types_list'] = $value;
        }
    }
}
