<?php

namespace Abs\GigoPkg;

use Abs\HelperPkg\Traits\SeederTrait;
use App\Company;
use App\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bay extends Model
{
    use SeederTrait;
	use SoftDeletes;
	protected $table = 'bays';
	public $timestamps = true;
	protected $fillable =
		["id","short_name","outlet_id","name","status_id","job_order_id"]
	;
}