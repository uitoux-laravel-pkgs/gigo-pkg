<?php

namespace Abs\GigoPkg;
use App\Config;
use App\Customer;
use App\Http\Controllers\Controller;
use App\JobOrder;
use App\VehicleModel;
use DB;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class VehicleInwardController extends Controller {

	public function __construct() {
		$this->data['theme'] = config('custom.theme');
	}

	public function getVehicleInwardFilter() {
		$params = [
			'config_type_id' => 37,
			'add_default' => true,
			'default_text' => "Select Status",
		];
		$this->data['extras'] = [
			'registration_type_list' => [
				['id' => '', 'name' => 'Select Registration Type'],
				['id' => '1', 'name' => 'Registered Vehicle'],
				['id' => '0', 'name' => 'Un-Registered Vehicle'],
			],
			'status_list' => Config::getDropDownList($params),
		];
		return response()->json($this->data);
	}

	public function getVehicleInwardList(Request $request) {

		$vehicle_inwards = JobOrder::company('job_orders')
			->join('gate_logs', 'gate_logs.job_order_id', 'job_orders.id')
			->leftJoin('vehicles', 'job_orders.vehicle_id', 'vehicles.id')
			->leftJoin('vehicle_owners', function ($join) {
				$join->on('vehicle_owners.vehicle_id', 'job_orders.vehicle_id')
					->whereRaw('vehicle_owners.from_date = (select MAX(vehicle_owners1.from_date) from vehicle_owners as vehicle_owners1 where vehicle_owners1.vehicle_id = job_orders.vehicle_id)');
			})
			->leftJoin('customers', 'customers.id', 'vehicle_owners.customer_id')
			->leftJoin('models', 'models.id', 'vehicles.model_id')
			->leftJoin('amc_members', 'amc_members.vehicle_id', 'vehicles.id')
			->leftJoin('amc_policies', 'amc_policies.id', 'amc_members.policy_id')
			->join('configs', 'configs.id', 'gate_logs.status_id')
			->select(
				'job_orders.id',
				DB::raw('IF(vehicles.is_registered = 1,"Registered Vehicle","Un-Registered Vehicle") as registration_type'),
				'vehicles.registration_number',
				DB::raw('COALESCE(models.model_number, "-") as model_number'),
				'gate_logs.number',
				'gate_logs.status_id',
				DB::raw('DATE_FORMAT(gate_logs.gate_in_date,"%d/%m/%Y - %h:%i %p") as date'),
				'job_orders.driver_name',
				'job_orders.driver_mobile_number as driver_mobile_number',
				DB::raw('COALESCE(GROUP_CONCAT(amc_policies.name), "-") as amc_policies'),
				'configs.name as status',
				DB::raw('COALESCE(customers.name, "-") as customer_name')
			)
			->whereRaw("IF (`gate_logs`.`status_id` = '8120', `job_orders`.`service_advisor_id` IS  NULL, `job_orders`.`service_advisor_id` = '" . $request->service_advisor_id . "')")
			->where(function ($query) use ($request) {
				if (!empty($request->gate_in_date)) {
					$query->whereDate('gate_logs.gate_in_date', date('Y-m-d', strtotime($request->gate_in_date)));
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->reg_no)) {
					$query->where('vehicles.registration_number', 'LIKE', '%' . $request->reg_no . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->membership)) {
					$query->where('amc_policies.name', 'LIKE', '%' . $request->membership . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->gate_in_no)) {
					$query->where('gate_logs.number', 'LIKE', '%' . $request->gate_in_no . '%');
				}
			})
			->where(function ($query) use ($request) {
				if ($request->registration_type == '1' || $request->registration_type == '0') {
					$query->where('vehicles.is_registered', $request->registration_type);
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->customer_id)) {
					$query->where('vehicle_owners.customer_id', $request->customer_id);
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->model_id)) {
					$query->where('vehicles.model_id', $request->model_id);
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->status_id)) {
					$query->where('gate_logs.status_id', $request->status_id);
				}
			})
			->groupBy('job_orders.id');

		return Datatables::of($vehicle_inwards)
			->rawColumns(['status', 'action'])
			->filterColumn('registration_type', function ($query, $keyword) {
				$sql = 'IF(vehicles.is_registered = 1,"Registered Vehicle","Un-Registered Vehicle")  like ?';
				$query->whereRaw($sql, ["%{$keyword}%"]);
			})
			->editColumn('status', function ($vehicle_inward) {
				$status = $vehicle_inward->status_id == '8120' ? 'blue' : 'green';
				return '<span class="text-' . $status . '">' . $vehicle_inward->status . '</span>';
			})
			->addColumn('action', function ($vehicle_inward) {
				$img1 = asset('public/themes/' . $this->data['theme'] . '/img/content/table/view.svg');
				$img1_active = asset('public/themes/' . $this->data['theme'] . '/img/content/table/view.svg');
				$output = '';
				$output .= '<a href="#!/inward-vehicle/vehicle-detail/' . $vehicle_inward->id . '" id = "" title="View"><img src="' . $img1 . '" alt="View" class="img-responsive" onmouseover=this.src="' . $img1 . '" onmouseout=this.src="' . $img1 . '"></a>';
				$output .= '<a href="#!/inward-vehicle/vehicle-detail/' . $vehicle_inward->id . '" id = "" title="View" class="btn btn-secondary-dark btn-xs">Initiate</a>';
				return $output;
			})
			->make(true);
	}

	public function getCustomerSearchList(Request $request) {
		return Customer::searchCustomer($request);
	}

	public function getVehicleModelSearchList(Request $request) {
		return VehicleModel::searchVehicleModel($request);
	}

}