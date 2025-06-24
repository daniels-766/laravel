<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
     protected $fillable = ['user_id', 'jenis', 'keterangan', 'jumlah'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
