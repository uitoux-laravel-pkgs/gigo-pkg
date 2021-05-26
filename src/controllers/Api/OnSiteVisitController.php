<?php

namespace Abs\GigoPkg\Api;

use Abs\SerialNumberPkg\SerialNumberGroup;
use Abs\TaxPkg\Tax;
use App\AmcCustomer;
use App\Attachment;
use App\Country;
use App\Customer;
use App\Employee;
use App\Entity;
use App\FinancialYear;
use App\Http\Controllers\Controller;
use App\Http\Controllers\WpoSoapController;
use App\OnSiteOrder;
use App\OnSiteOrderEstimate;
use App\OnSiteOrderIssuedPart;
use App\OnSiteOrderPart;
use App\OnSiteOrderRepairOrder;
use App\OnSiteOrderReturnedPart;
use App\OnSiteOrderTimeLog;
use App\Otp;
use App\Outlet;
use App\Part;
use App\PartStock;
use App\RepairOrder;
use App\ShortUrl;
use App\SplitOrderType;
use App\User;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Storage;
use Validator;

class OnSiteVisitController extends Controller
{
    public $successStatus = 200;

    public function __construct(WpoSoapController $getSoap = null)
    {
        $this->getSoap = $getSoap;
    }

    public function getLabourPartsData($params)
    {

        $result = array();

        $site_visit = OnSiteOrder::with([
            'company',
            'outlet',
            'onSiteVisitUser',
            'customer',
            'customer.amcCustomer',
            'customer.address',
            'customer.address.country',
            'customer.address.state',
            'customer.address.city',
            'outlet',
            'status',
            'onSiteOrderRepairOrders',
            'onSiteOrderRepairOrders.status',
            'onSiteOrderParts',
            'onSiteOrderParts.status',
            'photos',
        ])->where('id', $params['on_site_order_id'])->first();

        $customer_paid_type = SplitOrderType::where('paid_by_id', '10013')->pluck('id')->toArray();

        $labour_amount = 0;
        $part_amount = 0;

        $labour_details = array();
        $labours = array();

        $not_approved_labour_parts_count = 0;

        if ($site_visit->onSiteOrderRepairOrders) {
            foreach ($site_visit->onSiteOrderRepairOrders as $key => $value) {
                $labour_details[$key]['id'] = $value->id;
                $labour_details[$key]['labour_id'] = $value->repair_order_id;
                $labour_details[$key]['code'] = $value->repairOrder->code;
                $labour_details[$key]['name'] = $value->repairOrder->name;
                $labour_details[$key]['type'] = $value->repairOrder->repairOrderType ? $value->repairOrder->repairOrderType->short_name : '-';
                $labour_details[$key]['qty'] = $value->qty;
                $repair_order = $value->repairOrder;
                if ($value->repairOrder->is_editable == 1) {
                    $labour_details[$key]['rate'] = $value->amount;
                    $repair_order->amount = $value->amount;
                } else {
                    $labour_details[$key]['rate'] = $value->repairOrder->amount;
                }

                $labour_details[$key]['amount'] = $value->amount;
                $labour_details[$key]['split_order_type'] = $value->splitOrderType ? $value->splitOrderType->code . "|" . $value->splitOrderType->name : '-';
                $labour_details[$key]['removal_reason_id'] = $value->removal_reason_id;
                $labour_details[$key]['split_order_type_id'] = $value->split_order_type_id;
                $labour_details[$key]['repair_order'] = $repair_order;
                $labour_details[$key]['customer_voice'] = $value->customerVoice;
                $labour_details[$key]['customer_voice_id'] = $value->customer_voice_id;
                $labour_details[$key]['status_id'] = $value->status_id;
                $labour_details[$key]['status'] = $value->status->name;
                if (in_array($value->split_order_type_id, $customer_paid_type) || !$value->split_order_type_id) {
                    if ($value->is_free_service != 1 && $value->removal_reason_id == null) {
                        $labour_amount += $value->amount;
                        if ($value->is_customer_approved == 0) {
                            $not_approved_labour_parts_count++;
                        }
                    } else {
                        $labour_details[$key]['amount'] = 0;
                    }
                } else {
                    $labour_details[$key]['amount'] = 0;
                }

                $labours[$key]['id'] = $value->repair_order_id;
                $labours[$key]['code'] = $value->repairOrder->code;
                $labours[$key]['name'] = $value->repairOrder->name;
            }
        }

        $part_details = array();
        if ($site_visit->onSiteOrderParts) {
            foreach ($site_visit->onSiteOrderParts as $key => $value) {
                $part_details[$key]['id'] = $value->id;
                $part_details[$key]['part_id'] = $value->part_id;
                $part_details[$key]['code'] = $value->part->code;
                $part_details[$key]['name'] = $value->part->name;
                $part_details[$key]['type'] = $value->part->partType ? $value->part->partType->name : '-';
                $part_details[$key]['rate'] = $value->rate;
                $part_details[$key]['qty'] = $value->qty;
                $part_details[$key]['amount'] = $value->amount;
                $part_details[$key]['total_amount'] = $value->amount;
                $part_details[$key]['split_order_type'] = $value->splitOrderType ? $value->splitOrderType->code . "|" . $value->splitOrderType->name : '-';
                $part_details[$key]['removal_reason_id'] = $value->removal_reason_id;
                $part_details[$key]['split_order_type_id'] = $value->split_order_type_id;
                $part_details[$key]['part'] = $value->part;
                $part_details[$key]['status_id'] = $value->status_id;
                $part_details[$key]['status'] = $value->status->name;
                $part_details[$key]['customer_voice'] = $value->customerVoice;
                $part_details[$key]['customer_voice_id'] = $value->customer_voice_id;
                $part_details[$key]['repair_order'] = $value->part->repair_order_parts;

                if (in_array($value->split_order_type_id, $customer_paid_type) || !$value->split_order_type_id) {
                    if ($value->is_free_service != 1 && $value->removal_reason_id == null) {
                        $part_amount += $value->amount;
                        if ($value->is_customer_approved == 0) {
                            $not_approved_labour_parts_count++;
                        }
                    } else {
                        $part_details[$key]['amount'] = 0;
                    }
                } else {
                    $part_details[$key]['amount'] = 0;
                }
            }
        }

        $total_amount = $part_amount + $labour_amount;

        $result['site_visit'] = $site_visit;
        $result['labour_details'] = $labour_details;
        $result['part_details'] = $part_details;
        $result['labour_amount'] = $labour_amount;
        $result['part_amount'] = $part_amount;
        $result['total_amount'] = $total_amount;
        $result['labours'] = $labours;
        $result['not_approved_labour_parts_count'] = $not_approved_labour_parts_count;

        return $result;
    }

    public function getPartStockDetails(Request $request)
    {
        // dd($request->all());
        $part = Part::with([
            'uom',
            'partStock' => function ($query) use ($request) {
                $query->where('outlet_id', $request->outlet_id);
            },
            'taxCode',
            'taxCode.taxes',
        ])
            ->find($request->part_id);

        $data['part'] = $part;

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getFormData(Request $request)
    {
        // dd($request->all());
        if ($request->id) {
            $site_visit = OnSiteOrder::find($request->id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'Site Visit Detail Not Found!',
                    ],
                ]);
            }

            $params['on_site_order_id'] = $request->id;

            $result = $this->getLabourPartsData($params);

            $site_visit = $result['site_visit'];
            $amc_customer_status = 0;
            if ($site_visit && $site_visit->customer && $site_visit->customer->amcCustomer) {
                if (date('Y-m-d', strtotime($site_visit->customer->amcCustomer->expiry_date)) >= date('Y-m-d')) {
                    $amc_customer_status = 1;
                }

                if (date('Y-m-d', strtotime($site_visit->customer->amcCustomer->start_date)) <= date('Y-m-d')) {
                    $amc_customer_status = 1;
                } else {
                    $amc_customer_status = 0;
                }

                $remaining_services = $site_visit->customer->amcCustomer->remaining_services ? $site_visit->customer->amcCustomer->remaining_services : 0;

                if ($remaining_services > 0) {
                    $amc_customer_status = 1;
                } else {
                    $amc_customer_status = 0;
                }
            }

            //Check Estimate PDF Available or not
            $directoryPath = storage_path('app/public/on-site-visit/pdf/' . $site_visit->number . '_estimate.pdf');
            if (file_exists($directoryPath)) {
                $site_visit->estimate_pdf = url('storage/app/public/on-site-visit/pdf/' . $site_visit->number . '_estimate.pdf');
            } else {
                $site_visit->estimate_pdf = '';
            }

            //Check Revised Estimate PDF Available or not
            $directoryPath = storage_path('app/public/on-site-visit/pdf/' . $site_visit->number . '_revised_estimate.pdf');
            if (file_exists($directoryPath)) {
                $site_visit->revised_estimate_pdf = url('storage/app/public/on-site-visit/pdf/' . $site_visit->number . '_revised_estimate.pdf');
            } else {
                $site_visit->revised_estimate_pdf = '';
            }

            //Check Labour PDF Available or not
            $directoryPath = storage_path('app/public/on-site-visit/pdf/' . $site_visit->number . '_labour_invoice.pdf');
            if (file_exists($directoryPath)) {
                $site_visit->labour_pdf = url('storage/app/public/on-site-visit/pdf/' . $site_visit->number . '_labour_invoice.pdf');
            } else {
                $site_visit->labour_pdf = '';
            }

            //Check Part PDF Available or not
            $directoryPath = storage_path('app/public/on-site-visit/pdf/' . $site_visit->number . '_parts_invoice.pdf');
            if (file_exists($directoryPath)) {
                $site_visit->part_pdf = url('storage/app/public/on-site-visit/pdf/' . $site_visit->number . '_parts_invoice.pdf');
            } else {
                $site_visit->part_pdf = '';
            }

            //Check Bill Detail PDF Available or not
            $directoryPath = storage_path('app/public/on-site-visit/pdf/' . $site_visit->number . '_bill_details.pdf');
            if (file_exists($directoryPath)) {
                $site_visit->bill_detail_pdf = url('storage/app/public/on-site-visit/pdf/' . $site_visit->number . '_bill_details.pdf');
            } else {
                $site_visit->bill_detail_pdf = '';
            }

        } else {
            $site_visit = new OnSiteOrder;
            // $previous_number = OnSiteOrder::where('outlet_id',Auth::user()->working_outlet_id)->orderBy('id','desc')->first();
            // $site_visit->number =
            $result['site_visit'] = $site_visit;
            $result['part_details'] = [];
            $result['labour_details'] = [];
            $result['total_amount'] = 0;
            $result['labour_amount'] = 0;
            $result['part_amount'] = 0;
            $result['labours'] = [];
            $result['not_approved_labour_parts_count'] = 0;
            $amc_customer_status = 0;
        }

        $this->data['success'] = true;

        $extras = [
            'country_list' => Country::getDropDownList(),
            'state_list' => [], //State::getDropDownList(),
            'city_list' => [], //City::getDropDownList(),
        ];

        // $this->data['site_visit'] = $site_visit;
        $this->data['extras'] = $extras;

        return response()->json([
            'success' => true,
            'site_visit' => $result['site_visit'],
            'part_details' => $result['part_details'],
            'labour_details' => $result['labour_details'],
            'total_amount' => $result['total_amount'],
            'labour_amount' => $result['labour_amount'],
            'parts_rate' => $result['part_amount'],
            'labours' => $result['labours'],
            'not_approved_labour_parts_count' => $result['not_approved_labour_parts_count'],
            'extras' => $extras,
            'amc_customer_status' => $amc_customer_status,
            'country' => Country::find(1),
        ]);
    }

    public function saveLabourDetail(Request $request)
    {
        // dd($request->all());
        try {
            $error_messages = [
                'rot_id.unique' => 'Labour is already taken',
            ];

            $validator = Validator::make($request->all(), [
                'on_site_order_id' => [
                    'required',
                    'integer',
                    'exists:on_site_orders,id',
                ],
                'rot_id' => [
                    'required',
                    'integer',
                    'exists:repair_orders,id',
                    'unique:on_site_order_repair_orders,repair_order_id,' . $request->on_site_repair_order_id . ',id,on_site_order_id,' . $request->on_site_order_id,
                ],
                'split_order_type_id' => [
                    'required',
                    'integer',
                    'exists:split_order_types,id',
                ],
            ], $error_messages);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => $validator->errors()->all(),
                ]);
            }

            //Estimate Order ID
            $on_site_order = OnSiteOrder::find($request->on_site_order_id);

            $customer_paid_type = SplitOrderType::where('paid_by_id', '10013')->pluck('id')->toArray();

            if (!$on_site_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            DB::beginTransaction();

            $on_site_order->is_customer_approved = 0;
            // $on_site_order->status_id = 8463;
            $on_site_order->save();

            $estimate_id = OnSiteOrderEstimate::where('on_site_order_id', $on_site_order->id)->where('status_id', 10071)->first();
            if ($estimate_id) {
                $estimate_order_id = $estimate_id->id;
            } else {
                if (date('m') > 3) {
                    $year = date('Y') + 1;
                } else {
                    $year = date('Y');
                }
                //GET FINANCIAL YEAR ID
                $financial_year = FinancialYear::where('from', $year)
                    ->where('company_id', Auth::user()->company_id)
                    ->first();
                if (!$financial_year) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'Fiancial Year Not Found',
                        ],
                    ]);
                }
                //GET BRANCH/OUTLET
                $branch = Outlet::where('id', $on_site_order->outlet_id)->first();

                //GENERATE GATE IN VEHICLE NUMBER
                $generateNumber = SerialNumberGroup::generateNumber(151, $financial_year->id, $branch->state_id, $branch->id);
                if (!$generateNumber['success']) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'No Estimate Reference number found for FY : ' . $financial_year->year . ', State : ' . $branch->state->code . ', Outlet : ' . $branch->code,
                        ],
                    ]);
                }

                $estimate = new OnSiteOrderEstimate;
                $estimate->on_site_order_id = $on_site_order->id;
                $estimate->number = $generateNumber['number'];
                $estimate->status_id = 10071;
                $estimate->created_by_id = Auth::user()->id;
                $estimate->created_at = Carbon::now();
                $estimate->save();
                $estimate_order_id = $estimate->id;
            }

            $repair_order = RepairOrder::find($request->rot_id);
            if (!$repair_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'Repair order / Labour Not Found!',
                    ],
                ]);
            }

            if (!empty($request->on_site_repair_order_id)) {
                $on_site_repair_order = OnSiteOrderRepairOrder::find($request->on_site_repair_order_id);
                if ($on_site_repair_order) {
                    $on_site_repair_order->updated_by_id = Auth::user()->id;
                    $on_site_repair_order->updated_at = Carbon::now();
                    $on_site_repair_order->removal_reason_id = null;
                    $on_site_repair_order->removal_reason = null;
                } else {
                    $on_site_repair_order = new OnSiteOrderRepairOrder;
                    $on_site_repair_order->created_by_id = Auth::user()->id;
                    $on_site_repair_order->created_at = Carbon::now();
                }
            } else {
                $on_site_repair_order = new OnSiteOrderRepairOrder;
                $on_site_repair_order->created_by_id = Auth::user()->id;
                $on_site_repair_order->created_at = Carbon::now();
            }

            $on_site_repair_order->on_site_order_id = $request->on_site_order_id;
            $on_site_repair_order->repair_order_id = $request->rot_id;
            $on_site_repair_order->qty = $repair_order->hours;
            $on_site_repair_order->split_order_type_id = $request->split_order_type_id;
            $on_site_repair_order->estimate_order_id = $estimate_order_id;
            // if ($request->repair_order_description) {
            $on_site_repair_order->amount = isset($request->repair_order_amount) ? $request->repair_order_amount : $repair_order->amount;
            // } else {
            // $on_site_repair_order->amount = $repair_order->amount;
            // }

            if (in_array($request->split_order_type_id, $customer_paid_type)) {
                $on_site_repair_order->status_id = 8180; //Customer Approval Pending
                $on_site_repair_order->is_customer_approved = 0;
            } else {
                $on_site_repair_order->is_customer_approved = 1;
                $on_site_repair_order->status_id = 8181; //Mechanic Not Assigned
            }

            $on_site_repair_order->save();

            if ($on_site_order->is_customer_approved == 1) {
                $result = $this->getApprovedLabourPartsAmount($on_site_order->id);

                if ($result['status'] == 'true') {
                    if (in_array($request->split_order_type_id, $customer_paid_type)) {
                        $on_site_order->status_id = 8200; //Customer Approval Pending
                        $on_site_order->is_customer_approved = 0;
                        $on_site_order->save();
                    }
                } else {
                    OnSiteOrderPart::where('on_site_order_id', $on_site_order->id)->where('is_customer_approved', 0)->where('status_id', 8200)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8201, 'updated_at' => Carbon::now()]);

                    OnSiteOrderRepairOrder::where('on_site_order_id', $on_site_order->id)->where('is_customer_approved', 0)->where('status_id', 8180)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8181, 'updated_at' => Carbon::now()]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Labour detail saved successfully!!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    public function savePartsDetail(Request $request)
    {
        // dd($request->all());
        try {
            $validator = Validator::make($request->all(), [
                'on_site_order_id' => [
                    'required',
                    'integer',
                    'exists:on_site_orders,id',
                ],
                'part_id' => [
                    'required',
                    'integer',
                    'exists:parts,id',
                ],

                /*'split_order_id' => [
                'required',
                'integer',
                'exists:split_order_types,id',
                ],*/
                'qty' => [
                    'required',
                    'numeric',
                ],

            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => $validator->errors()->all(),
                ]);
            }

            //Estimate Order ID
            $on_site_order = OnSiteOrder::find($request->on_site_order_id);

            $customer_paid_type = SplitOrderType::where('paid_by_id', '10013')->pluck('id')->toArray();

            if (!$on_site_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            DB::beginTransaction();

            $on_site_order->is_customer_approved = 0;
            // $on_site_visit->status_id = 8463;
            $on_site_order->save();

            $estimate_id = OnSiteOrderEstimate::where('on_site_order_id', $on_site_order->id)->where('status_id', 10071)->first();
            if ($estimate_id) {
                $estimate_order_id = $estimate_id->id;
            } else {
                if (date('m') > 3) {
                    $year = date('Y') + 1;
                } else {
                    $year = date('Y');
                }
                //GET FINANCIAL YEAR ID
                $financial_year = FinancialYear::where('from', $year)
                    ->where('company_id', Auth::user()->company_id)
                    ->first();
                if (!$financial_year) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'Fiancial Year Not Found',
                        ],
                    ]);
                }
                //GET BRANCH/OUTLET
                $branch = Outlet::where('id', $on_site_order->outlet_id)->first();

                //GENERATE GATE IN VEHICLE NUMBER
                $generateNumber = SerialNumberGroup::generateNumber(151, $financial_year->id, $branch->state_id, $branch->id);
                if (!$generateNumber['success']) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'No Estimate Reference number found for FY : ' . $financial_year->year . ', State : ' . $branch->state->code . ', Outlet : ' . $branch->code,
                        ],
                    ]);
                }

                $estimate = new OnSiteOrderEstimate;
                $estimate->on_site_order_id = $on_site_order->id;
                $estimate->number = $generateNumber['number'];
                $estimate->status_id = 10071;
                $estimate->created_by_id = Auth::user()->id;
                $estimate->created_at = Carbon::now();
                $estimate->save();

                $estimate_order_id = $estimate->id;
            }

            $customer_paid_type = SplitOrderType::where('paid_by_id', '10013')->pluck('id')->toArray();

            $part = Part::with(['partStock'])->where('id', $request->part_id)->first();
            if (!$part) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'Part Not Found',
                    ],
                ]);
            }

            $request_qty = $request->qty;

            if (!empty($request->on_site_part_id)) {
                $on_site_part = OnSiteOrderPart::find($request->on_site_part_id);
                if ($on_site_part) {
                    $on_site_part->updated_by_id = Auth::user()->id;
                    $on_site_part->updated_at = Carbon::now();
                    $on_site_part->removal_reason_id = null;
                    $on_site_part->removal_reason = null;
                } else {
                    $on_site_part = new OnSiteOrderPart;
                    $on_site_part->created_by_id = Auth::user()->id;
                    $on_site_part->created_at = Carbon::now();
                }
            } else {
                //Check Request parts are already requested or not.
                $on_site_part = OnSiteOrderPart::where('on_site_order_id', $request->on_site_order_id)->where('part_id', $request->part_id)->where('status_id', 8200)->where('is_customer_approved', 0)->whereNull('removal_reason_id')->first();
                if ($on_site_part) {
                    $request_qty = $on_site_part->qty + $request->qty;
                    $on_site_part->updated_by_id = Auth::user()->id;
                    $on_site_part->updated_at = Carbon::now();
                } else {
                    $on_site_part = new OnSiteOrderPart;
                    $on_site_part->created_by_id = Auth::user()->id;
                    $on_site_part->created_at = Carbon::now();
                }
                $on_site_part->estimate_order_id = $estimate_order_id;
            }

            $part_mrp = $request->mrp ? $request->mrp : 0;
            $on_site_part->on_site_order_id = $request->on_site_order_id;
            $on_site_part->part_id = $request->part_id;

            $on_site_part->rate = $part_mrp;
            $on_site_part->qty = $request_qty;
            $on_site_part->split_order_type_id = $request->split_order_type_id;
            $on_site_part->amount = $request_qty * $part_mrp;

            if (!$request->split_order_type_id || in_array($request->split_order_type_id, $customer_paid_type)) {
                $on_site_part->status_id = 8200; //Customer Approval Pending
                $on_site_part->is_customer_approved = 0;
            } else {
                $on_site_part->is_customer_approved = 1;
                $on_site_part->status_id = 8201; //Not Issued
            }

            $on_site_part->save();

            if ($on_site_order->is_customer_approved == 1) {
                $result = $this->getApprovedLabourPartsAmount($on_site_order->id);

                if ($result['status'] == 'true') {
                    if (in_array($request->split_order_type_id, $customer_paid_type)) {
                        $on_site_order->status_id = 8200; //Customer Approval Pending
                        $on_site_order->is_customer_approved = 0;
                        $on_site_order->save();
                    }
                } else {
                    OnSiteOrderPart::where('on_site_order_id', $on_site_order->id)->where('is_customer_approved', 0)->where('status_id', 8200)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8201, 'updated_at' => Carbon::now()]);

                    OnSiteOrderRepairOrder::where('on_site_order_id', $on_site_order->id)->where('is_customer_approved', 0)->where('status_id', 8180)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8181, 'updated_at' => Carbon::now()]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Part detail saved Successfully!!',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    public function getApprovedLabourPartsAmount($site_visit_id)
    {

        $customer_paid_type = SplitOrderType::where('paid_by_id', '10013')->pluck('id')->toArray();

        $site_visit = OnSiteOrder::with([
            'company',
            'outlet',
            'onSiteVisitUser',
            'customer',
            'customer.address',
            'customer.address.country',
            'customer.address.state',
            'customer.address.city',
            'status',
            'onSiteOrderRepairOrders' => function ($q) {
                $q->whereNull('removal_reason_id');
            },
            'onSiteOrderParts' => function ($q) {
                $q->whereNull('removal_reason_id');
            },
        ])->find($site_visit_id);

        if ($site_visit->customer->primaryAddress) {
            //Check which tax applicable for customer
            if ($site_visit->outlet->state_id == $site_visit->customer->primaryAddress->state_id) {
                $tax_type = 1160; //Within State
            } else {
                $tax_type = 1161; //Inter State
            }
        } else {
            $tax_type = 1160; //Within State
        }

        $taxes = Tax::whereIn('id', [1, 2, 3])->get();

        $parts_amount = 0;
        $labour_amount = 0;
        $total_billing_amount = 0;

        if ($site_visit->onSiteOrderRepairOrders) {
            foreach ($site_visit->onSiteOrderRepairOrders as $key => $labour) {
                if ($labour->is_free_service != 1 && (in_array($labour->split_order_type_id, $customer_paid_type) || !$labour->split_order_type_id)) {
                    $total_amount = 0;
                    $tax_amount = 0;
                    if ($labour->repairOrder->taxCode) {
                        foreach ($labour->repairOrder->taxCode->taxes as $tax_key => $value) {
                            $percentage_value = 0;
                            if ($value->type_id == $tax_type) {
                                $percentage_value = ($labour->amount * $value->pivot->percentage) / 100;
                                $percentage_value = number_format((float) $percentage_value, 2, '.', '');
                            }
                            $tax_amount += $percentage_value;
                        }
                    }

                    $total_amount = $tax_amount + $labour->amount;
                    $total_amount = number_format((float) $total_amount, 2, '.', '');
                    $labour_amount += $total_amount;
                }
            }
        }

        if ($site_visit->onSiteOrderParts) {
            foreach ($site_visit->onSiteOrderParts as $key => $parts) {
                if ($parts->is_free_service != 1 && (in_array($parts->split_order_type_id, $customer_paid_type) || !$parts->split_order_type_id)) {
                    $total_amount = 0;

                    $tax_amount = 0;
                    if ($parts->part->taxCode) {
                        if (count($parts->part->taxCode->taxes) > 0) {
                            foreach ($parts->part->taxCode->taxes as $tax_key => $value) {
                                $percentage_value = 0;
                                if ($value->type_id == $tax_type) {
                                    $percentage_value = ($parts->amount * $value->pivot->percentage) / 100;
                                    $percentage_value = number_format((float) $percentage_value, 2, '.', '');
                                }
                                $tax_amount += $percentage_value;
                            }
                        }
                    }

                    // $total_amount = $tax_amount + $parts->amount;
                    $total_amount = $parts->amount;
                    $total_amount = number_format((float) $total_amount, 2, '.', '');
                    $parts_amount += $total_amount;
                }
            }
        }

        $total_billing_amount = $parts_amount + $labour_amount;

        $total_billing_amount = round($total_billing_amount);

        if ($total_billing_amount > $site_visit->approved_amount) {
            // return $total_billing_amount;
            $result['status'] = 'true';
            $result['total_billing_amount'] = $total_billing_amount;
        } else {
            $result['status'] = 'false';
            $result['total_billing_amount'] = $total_billing_amount;
        }

        return $result;
    }

    //Send SMS to Customer for AMC Request
    //Save AMC Customer details
    public function amcCustomerSave(Request $request)
    {
        // dd($request->all());
        try {

            $on_site_order = OnSiteOrder::with(['customer'])->find($request->id);

            if (!$on_site_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            if ($request->type_id == 1) {
                if (!$on_site_order->customer) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Not Found!'],
                    ]);
                }

                $customer_mobile = $on_site_order->customer->mobile_no;

                if (!$customer_mobile) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Mobile Number Not Found!'],
                    ]);
                }

                DB::beginTransaction();

                $message = 'Thanks for the interest';

                $msg = sendSMSNotification($customer_mobile, $message);

                DB::commit();

                $message = 'Message Sent successfully!!';

            } elseif ($request->type_id == 2) {

                if (strtotime($request->amc_starting_date) >= strtotime($request->amc_ending_date)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'AMC Ending Date should be greater than AMC Starting Date',
                        ],
                    ]);
                }

                $amc_customer = AmcCustomer::firstOrNew(['customer_id' => $request->customer_id, 'amc_customer_type_id' => 2, 'tvs_one_customer_code' => $request->amc_customer_code]);

                if ($amc_customer->exists) {
                    $amc_customer->updated_by_id = Auth::user()->id;
                    $amc_customer->updated_at = Carbon::now();
                } else {
                    $amc_customer->total_services = 12;
                    $amc_customer->remaining_services = 12;
                    $amc_customer->created_by_id = Auth::user()->id;
                    $amc_customer->created_at = Carbon::now();
                }
                $amc_customer->start_date = date('Y-m-d', strtotime($request->amc_starting_date));
                $amc_customer->expiry_date = date('Y-m-d', strtotime($request->amc_ending_date));
                $amc_customer->save();

                $message = 'AMC Customer details saved successfully!';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    public function sendCustomerOtp(Request $request)
    {
        // dd($request->all());
        try {

            $on_site_order = OnSiteOrder::with(['customer'])->find($request->id);

            if (!$on_site_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            if (!$on_site_order->customer) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['Customer Not Found!'],
                ]);
            }

            $customer_mobile = $on_site_order->customer->mobile_no;

            if (!$customer_mobile) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['Customer Mobile Number Not Found!'],
                ]);
            }

            DB::beginTransaction();

            $otp_no = mt_rand(111111, 999999);
            $on_site_order_otp_update = OnSiteOrder::where('id', $request->id)
                ->update([
                    'otp_no' => $otp_no,
                    'status_id' => 6, //Waiting for Customer Approval
                    'is_customer_approved' => 0,
                    'updated_by_id' => Auth::user()->id,
                    'updated_at' => Carbon::now(),
                ]);

            $site_visit_estimates = OnSiteOrderEstimate::where('on_site_order_id', $on_site_order->id)->count();

            //Type 1 -> Estimate
            //Type 2 -> Revised Estimate
            $type = 1;
            if ($site_visit_estimates > 1) {
                $type = 2;
            }

            //Generate PDF
            $generate_on_site_estimate_pdf = OnSiteOrder::generateEstimatePDF($on_site_order->id, $type);

            DB::commit();
            if (!$on_site_order_otp_update) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['On Site Order OTP Update Failed!'],
                ]);
            }

            $current_time = date("Y-m-d H:m:s");

            $expired_time = Entity::where('entity_type_id', 32)->select('name')->first();
            if ($expired_time) {
                $expired_time = date("Y-m-d H:i:s", strtotime('+' . $expired_time->name . ' hours', strtotime($current_time)));
            } else {
                $expired_time = date("Y-m-d H:i:s", strtotime('+1 hours', strtotime($current_time)));
            }

            //Otp Save
            $otp = new Otp;
            $otp->entity_type_id = 10113;
            $otp->entity_id = $on_site_order->id;
            $otp->otp_no = $otp_no;
            $otp->created_by_id = Auth::user()->id;
            $otp->created_at = $current_time;
            $otp->expired_at = $expired_time;
            $otp->outlet_id = Auth::user()->employee->outlet_id;
            $otp->save();

            $message = 'OTP is ' . $otp_no . ' for Job Order Estimate. Please show this SMS to Our Service Executive to verify your Job Order Estimate';

            $msg = sendSMSNotification($customer_mobile, $message);

            return response()->json([
                'success' => true,
                'mobile_number' => $customer_mobile,
                'message' => 'OTP Sent successfully!!',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    public function verifyOtp(Request $request)
    {
        // dd($request->all());
        try {

            $validator = Validator::make($request->all(), [
                'on_site_order_id' => [
                    'required',
                    'exists:on_site_orders,id',
                    'integer',
                ],
                'otp_no' => [
                    'required',
                    'min:8',
                    'integer',
                ],
                'verify_otp' => [
                    'required',
                    'integer',
                ],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => $validator->errors()->all(),
                ]);
            }

            $on_site_order = OnSiteOrder::find($request->on_site_order_id);

            if (!$on_site_order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['On Site Order Not Found!'],
                ]);
            }

            DB::beginTransaction();

            $otp_validate = OnSiteOrder::where('id', $request->on_site_order_id)
                ->where('otp_no', '=', $request->otp_no)
                ->first();
            if (!$otp_validate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['On Site Order Approve Behalf of Customer OTP is wrong. Please try again.'],
                ]);
            }

            $current_time = date("Y-m-d H:m:s");

            //Validate OTP -> Expired or Not
            $otp_validate = OTP::where('entity_type_id', 10113)->where('entity_id', $request->on_site_order_id)->where('otp_no', '=', $request->otp_no)->where('expired_at', '>=', $current_time)
                ->first();
            if (!$otp_validate) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => ['OTP Expired!'],
                ]);
            }

            //UPDATE STATUS
            if ($on_site_order->status_id == 6) {
                $on_site_order->status_id = 8; //Estimation approved onbehalf of customer
            }
            $on_site_order->is_customer_approved = 1;
            // if ($request->revised_estimate_amount) {
            //     $job_order_status_update->estimated_amount = $request->revised_estimate_amount;
            // }
            $on_site_order->customer_approved_date_time = Carbon::now();
            $on_site_order->updated_at = Carbon::now();
            $on_site_order->save();

            //UPDATE REPAIR ORDER STATUS
            OnSiteOrderRepairOrder::where('on_site_order_id', $request->on_site_order_id)->where('is_customer_approved', 0)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8181, 'updated_by_id' => Auth::user()->id, 'updated_at' => Carbon::now()]);

            //UPDATE PARTS STATUS
            OnSiteOrderPart::where('on_site_order_id', $request->on_site_order_id)->where('is_customer_approved', 0)->whereNull('removal_reason_id')->update(['is_customer_approved' => 1, 'status_id' => 8201, 'updated_by_id' => Auth::user()->id, 'updated_at' => Carbon::now()]);

            OnSiteOrderEstimate::where('on_site_order_id', $request->on_site_order_id)->where('status_id', 10071)->update(['status_id' => 10072, 'updated_by_id' => Auth::user()->id, 'updated_at' => Carbon::now()]);

            $result = $this->getApprovedLabourPartsAmount($on_site_order->id);
            if ($result['status'] == 'true') {
                $on_site_order->approved_amount = $result['total_billing_amount'];
                $on_site_order->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer Approved Successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    public function save(Request $request)
    {
        // dd($request->all());
        try {
            if ($request->save_type_id == 1) {
                $error_messages = [
                    'customer_remarks.required' => "Customer Remarks is required",
                ];
                $validator = Validator::make($request->all(), [
                    'customer_remarks' => [
                        'required',
                    ],
                    'planned_visit_date' => [
                        'required',
                    ],
                    'code' => [
                        'required',
                    ],
                ], $error_messages);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                DB::beginTransaction();

                if ($request->on_site_order_id) {
                    $site_visit = OnSiteOrder::find($request->on_site_order_id);
                    if (!$site_visit) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Site Visit Detail Not Found!',
                            ],
                        ]);
                    }
                    $site_visit->updated_by_id = Auth::id();
                    $site_visit->updated_at = Carbon::now();
                } else {
                    $site_visit = new OnSiteOrder;
                    $site_visit->company_id = Auth::user()->company_id;
                    $site_visit->outlet_id = Auth::user()->working_outlet_id;
                    $site_visit->on_site_visit_user_id = Auth::user()->id;

                    if (date('m') > 3) {
                        $year = date('Y') + 1;
                    } else {
                        $year = date('Y');
                    }
                    //GET FINANCIAL YEAR ID
                    $financial_year = FinancialYear::where('from', $year)
                        ->where('company_id', Auth::user()->company_id)
                        ->first();
                    if (!$financial_year) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Fiancial Year Not Found',
                            ],
                        ]);
                    }
                    //GET BRANCH/OUTLET
                    $branch = Outlet::where('id', Auth::user()->working_outlet_id)->first();

                    //GENERATE GATE IN VEHICLE NUMBER
                    $generateNumber = SerialNumberGroup::generateNumber(152, $financial_year->id, $branch->state_id, $branch->id);
                    if (!$generateNumber['success']) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'No Site Visit number found for FY : ' . $financial_year->year . ', State : ' . $branch->state->code . ', Outlet : ' . $branch->code,
                            ],
                        ]);
                    }

                    // dd($generateNumber);
                    $site_visit->number = $generateNumber['number'];
                    $site_visit->created_by_id = Auth::id();
                    $site_visit->created_at = Carbon::now();
                    $site_visit->status_id = 1;
                }

                //save customer
                $customer = Customer::saveCustomer($request->all());
                $customer->saveAddress($request->all());

                $site_visit->customer_id = $customer->id;
                $site_visit->planned_visit_date = date('Y-m-d', strtotime($request->planned_visit_date));
                $site_visit->customer_remarks = $request->customer_remarks;

                $site_visit->save();

                $message = "On Site Visit Saved Successfully!";

                DB::commit();

            } elseif ($request->save_type_id == 2) {
                $validator = Validator::make($request->all(), [
                    'se_remarks' => [
                        'required',
                    ],
                    'parts_requirements' => [
                        'required',
                    ],
                    'on_site_order_id' => [
                        'required',
                        'exists:on_site_orders,id',
                    ],
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                DB::beginTransaction();

                $site_visit = OnSiteOrder::find($request->on_site_order_id);
                if (!$site_visit) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'Site Visit Detail Not Found!',
                        ],
                    ]);
                }

                if (!$site_visit->actual_visit_date) {
                    $site_visit->actual_visit_date = date('Y-m-d');
                }

                $site_visit->se_remarks = $request->se_remarks;
                $site_visit->parts_requirements = $request->parts_requirements;
                $site_visit->status_id = 2;
                $site_visit->updated_by_id = Auth::id();
                $site_visit->updated_at = Carbon::now();
                $site_visit->save();

                //REMOVE ATTACHMENTS
                if (isset($request->attachment_removal_ids)) {
                    $attachment_removal_ids = json_decode($request->attachment_removal_ids);
                    if (!empty($attachment_removal_ids)) {
                        Attachment::whereIn('id', $attachment_removal_ids)->forceDelete();
                    }
                }

                //Save Attachments
                $attachement_path = storage_path('app/public/gigo/on-site/');
                Storage::makeDirectory($attachement_path, 0777);
                // dd($request->all());
                if (isset($request->photos)) {
                    foreach ($request->photos as $key => $photo) {

                        $value = rand(1, 100);
                        $image = $photo;
                        $file_name_with_extension = $image->getClientOriginalName();
                        $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);
                        $extension = $image->getClientOriginalExtension();

                        $name = $site_visit->id . '_' . $file_name . '_' . rand(10, 1000) . '.' . $extension;

                        $photo->move($attachement_path, $name);
                        $attachement = new Attachment;
                        $attachement->attachment_of_id = 9124;
                        $attachement->attachment_type_id = 244;
                        $attachement->entity_id = $site_visit->id;
                        $attachement->name = $name;
                        $attachement->path = isset($request->attachment_descriptions[$key]) ? $request->attachment_descriptions[$key] : null;
                        $attachement->save();
                    }
                }

                $message = "On Site Visit Updated Successfully!";

                DB::commit();

            } else {
                $validator = Validator::make($request->all(), [
                    'job_card_number' => [
                        'required',
                        'unique:on_site_orders,job_card_number,' . $request->on_site_order_id . ',id,company_id,' . Auth::user()->company_id,
                    ],
                    'on_site_order_id' => [
                        'required',
                        'exists:on_site_orders,id',
                    ],
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                DB::beginTransaction();

                $site_visit = OnSiteOrder::find($request->on_site_order_id);
                if (!$site_visit) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'Site Visit Detail Not Found!',
                        ],
                    ]);
                }

                $site_visit->job_card_number = $request->job_card_number;
                $site_visit->status_id = 13;
                $site_visit->updated_by_id = Auth::id();
                $site_visit->updated_at = Carbon::now();
                $site_visit->save();

                $message = "Job Card Number Saved Successfully!";

                DB::commit();
            }

            //Send Approved Mail for user
            // $this->vehiceRequestMail($job_order->id, $type = 2);

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    //BULK ISSUE PART FORM DATA
    public function getBulkIssuePartFormData(Request $request)
    {
        // dd($request->all());
        try {
            $site_visit = OnSiteOrder::with([
                'company',
                'outlet',
                'onSiteVisitUser',
                'customer',
                'customer.address',
                'customer.address.country',
                'customer.address.state',
                'customer.address.city',
                'outlet',
                'status',
                'onSiteOrderRepairOrders',
                'onSiteOrderParts',
            ])->find($request->id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            $on_site_order_parts = Part::join('on_site_order_parts', 'on_site_order_parts.part_id', 'parts.id')->where('on_site_order_parts.on_site_order_id', $request->id)->whereNull('on_site_order_parts.removal_reason_id')
            // ->where('on_site_order_parts.is_customer_approved', 1)
                ->select('on_site_order_parts.id as on_site_order_part_id', 'on_site_order_parts.qty', 'parts.code', 'parts.name', 'parts.id')->get();

            $parts_data = array();

            // dd($on_site_order_parts);
            if ($on_site_order_parts) {
                foreach ($on_site_order_parts as $key => $parts) {
                    // dump($parts->code, $parts->id);

                    //Issued Qty
                    $issued_qty = OnSiteOrderIssuedPart::where('on_site_order_part_id', $parts->on_site_order_part_id)->sum('issued_qty');

                    //Returned Qty
                    $returned_qty = OnSiteOrderReturnedPart::where('on_site_order_part_id', $parts->on_site_order_part_id)->sum('returned_qty');

                    //Available Qty
                    $avail_qty = PartStock::where('part_id', $parts->id)->where('outlet_id', $site_visit->outlet_id)->pluck('stock')->first();

                    $total_remain_qty = ($parts->qty + $returned_qty) - $issued_qty;
                    $total_issued_qty = $issued_qty - $returned_qty;

                    // dump($avail_qty, $total_remain_qty);
                    // if ($avail_qty && $avail_qty > 0 && $total_remain_qty > 0) {
                    if ($total_remain_qty > 0) {
                        $parts_data[$key]['part_id'] = $parts->id;
                        $parts_data[$key]['code'] = $parts->code;
                        $parts_data[$key]['name'] = $parts->name;
                        $parts_data[$key]['on_site_order_part_id'] = $parts->on_site_order_part_id;
                        $parts_data[$key]['total_avail_qty'] = $avail_qty;
                        $parts_data[$key]['total_request_qty'] = $parts->qty;
                        $parts_data[$key]['total_issued_qty'] = $total_issued_qty;
                        $parts_data[$key]['total_remaining_qty'] = $total_remain_qty;
                    }
                }
            }

            $responseArr = array(
                'success' => true,
                'site_visit' => $site_visit,
                'on_site_order_parts' => $parts_data,
                'mechanic_id' => $site_visit->on_site_visit_user_id,
                // 'issue_modes' => $issue_modes,
            );

            return response()->json($responseArr);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    //SAVE STOCK INCHAGRE > ISSUED PART
    public function saveIssuedPart(Request $request)
    {
        // dd($request->all());
        try {
            if ($request->part_type == 3) {
                $validator = Validator::make($request->all(), [
                    'on_site_order_id' => [
                        'required',
                        'exists:on_site_orders,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                DB::beginTransaction();

                if ($request->issued_part) {
                    foreach ($request->issued_part as $key => $issued_part) {
                        if (isset($issued_part['qty'])) {
                            $on_site_order_isssued_part = new OnSiteOrderIssuedPart;
                            $on_site_order_isssued_part->on_site_order_part_id = $issued_part['on_site_order_part_id'];

                            $on_site_order_isssued_part->issued_qty = $issued_part['qty'];
                            $on_site_order_isssued_part->issued_mode_id = 8480;
                            $on_site_order_isssued_part->issued_to_id = $request->issued_to_id;
                            $on_site_order_isssued_part->created_by_id = Auth::user()->id;
                            $on_site_order_isssued_part->created_at = Carbon::now();
                            $on_site_order_isssued_part->save();

                            $on_site_order_part = OnSiteOrderPart::find($issued_part['on_site_order_part_id']);
                            $on_site_order_part->status_id = 8202; //Issued
                            $on_site_order_part->save();
                        }
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Parts not found!'],
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Part Issued Successfully!!',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    //START LABOUR WORK & ISSUE PART
    public function processLabourPart(Request $request)
    {
        // dd($request->all());
        try {

            if ($request->type == 'labour') {
                $validator = Validator::make($request->all(), [
                    'id' => [
                        'required',
                        'exists:on_site_order_repair_orders,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $on_site_order_repair_order = OnSiteOrderRepairOrder::find($request->id);
                $on_site_order_repair_order->status_id = 8183; //Start Work
                $on_site_order_repair_order->updated_by_id = Auth::user()->id;
                $on_site_order_repair_order->updated_at = Carbon::now();
                $on_site_order_repair_order->save();

                $message = 'Work Started Successfully!';
            } else {
                $validator = Validator::make($request->all(), [
                    'id' => [
                        'required',
                        'exists:on_site_order_parts,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $on_site_order_part = OnSiteOrderPart::find($request->id);
                $on_site_order_part->status_id = 8202; //Issued
                $on_site_order_part->updated_by_id = Auth::user()->id;
                $on_site_order_part->updated_at = Carbon::now();
                $on_site_order_part->save();

                $message = 'Part issued Successfully!';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    //DELETE ISSUE/RETURN PART DATA
    public function deleteIssueReturnParts(Request $request)
    {
        // dd($request->all());
        try {

            if ($request->type == 'Part Returned') {
                $validator = Validator::make($request->all(), [
                    'id' => [
                        'required',
                        'exists:on_site_order_returned_parts,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $returned_part = OnSiteOrderReturnedPart::where('id', $request->id)->forceDelete();

            } else {
                $validator = Validator::make($request->all(), [
                    'id' => [
                        'required',
                        'exists:on_site_order_issued_parts,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $issued_part = OnSiteOrderIssuedPart::where('id', $request->id)->forceDelete();

            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Part Deleted Successfully Successfully!!',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    //Return Part save form
    public function returnParts(Request $request)
    {
        // dd($request->all());
        try {
            if ($request->type_id == 2) {
                $validator = Validator::make($request->all(), [
                    'on_site_order_id' => [
                        'required',
                        'exists:on_site_orders,id',
                    ],
                    'on_site_order_part_id' => [
                        'required',
                        'exists:on_site_order_parts,id',
                    ],

                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $site_visit = OnSiteOrder::find($request->on_site_order_id);

                if (!$site_visit) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'On Site Visit Not Found!',
                        ],
                    ]);
                }

                if ($request->returned_qty) {
                    DB::beginTransaction();

                    //Total Qty
                    $parts = OnSiteOrderPart::where('id', $request->on_site_order_part_id)->first();
                    $total_qty = $parts->qty;

                    //Issued Qty
                    $issued_qty = OnSiteOrderIssuedPart::where('on_site_order_part_id', $parts->id)->sum('issued_qty');

                    //Returned Qty
                    $returned_qty = OnSiteOrderReturnedPart::where('on_site_order_part_id', $parts->id)->sum('returned_qty');

                    $total_remain_qty = ($issued_qty + $returned_qty);

                    if ($total_remain_qty >= $request->returned_qty) {
                        $returned_part = new OnSiteOrderReturnedPart;
                        $returned_part->on_site_order_part_id = $parts->id;
                        $returned_part->returned_qty = $request->returned_qty;
                        $returned_part->returned_to_id = $site_visit->on_site_visit_user_id;
                        $returned_part->remarks = $request->remarks;
                        $returned_part->created_by_id = Auth::user()->id;
                        $returned_part->created_at = Carbon::now();
                        $returned_part->save();
                    } else {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Invalid Returned Qty!',
                            ],
                        ]);
                    }

                    DB::commit();
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => [
                            'Invalid Returned Qty!',
                        ],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Part Returned Successfully!!',
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    //Get issue/return form data
    public function getPartsData(Request $request)
    {
        // dd($request->all());
        try {
            $site_visit = OnSiteOrder::find($request->id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            $on_site_order_parts = OnSiteOrderPart::join('parts', 'on_site_order_parts.part_id', 'parts.id')->where('on_site_order_parts.on_site_order_id', $request->id)->whereNull('on_site_order_parts.removal_reason_id')->select('parts.name', 'parts.code', 'on_site_order_parts.id as on_site_order_part_id')->get();

            $part_logs = array();
            $issued_parts = 0;

            if ($on_site_order_parts) {

                $parts_issue_logs = OnSiteOrderIssuedPart::join('on_site_order_parts', 'on_site_order_parts.id', 'on_site_order_issued_parts.on_site_order_part_id')
                    ->join('parts', 'on_site_order_parts.part_id', 'parts.id')
                    ->join('configs', 'on_site_order_issued_parts.issued_mode_id', 'configs.id')
                    ->join('users', 'on_site_order_issued_parts.issued_to_id', 'users.id')
                    ->where('on_site_order_parts.on_site_order_id', $request->id)
                    ->select(DB::raw('"Part Issued" as transaction_type'),
                        'parts.name',
                        'parts.code',
                        'on_site_order_issued_parts.issued_qty as qty',
                        DB::raw('"-" as remarks'),
                        DB::raw('DATE_FORMAT(on_site_order_issued_parts.created_at,"%d/%m/%Y") as date'),
                        'configs.name as issue_mode',
                        'users.name as mechanic',
                        'users.id as employee_id',
                        'on_site_order_issued_parts.id as job_order_part_issue_return_id',
                        'parts.id as part_id')
                // ->get()
                ;

                $issued_parts = $parts_issue_logs->get()->count();

                $parts_return_logs = OnSiteOrderReturnedPart::join('on_site_order_parts', 'on_site_order_parts.id', 'on_site_order_returned_parts.on_site_order_part_id')
                    ->join('parts', 'on_site_order_parts.part_id', 'parts.id')
                    ->join('users', 'on_site_order_returned_parts.returned_to_id', 'users.id')
                    ->select(
                        DB::raw('"Part Returned" as transaction_type'),
                        'parts.name',
                        'parts.code',
                        'on_site_order_returned_parts.returned_qty as qty',
                        'on_site_order_returned_parts.remarks',
                        DB::raw('DATE_FORMAT(on_site_order_returned_parts.created_at,"%d/%m/%Y") as date'),
                        DB::raw('"-" as issue_mode'),
                        'users.name as mechanic',
                        'users.id as employee_id',
                        'on_site_order_returned_parts.id as job_order_part_issue_return_id',
                        'parts.id as part_id'

                    )->union($parts_issue_logs)->orderBy('date', 'DESC')->get();

                $part_logs = $parts_return_logs;
            }

            return response()->json([
                'success' => true,
                'part_logs' => $part_logs,
                // 'issued_parts' => $issued_parts,
                'on_site_order_parts' => $on_site_order_parts,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    public function saveRequest(Request $request)
    {
        // dd($request->all());
        try {
            $site_visit = OnSiteOrder::with(['customer'])->find($request->id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'Site Visit Not Found!',
                    ],
                ]);
            }

            DB::beginTransaction();

            //Send Request to parts incharge for Add parts
            if ($request->type_id == 1) {
                $site_visit->status_id = 4;
                $message = 'On Site Visit Updated Successfully!';
            }
            //parts Estimation Completed
            elseif ($request->type_id == 2) {
                $site_visit->status_id = 5;
                $message = 'On Site Visit Updated Successfully!';
            }
            //Send message to customer for approve the estimate
            elseif ($request->type_id == 3) {
                $site_visit->status_id = 6;
                $otp_no = mt_rand(111111, 999999);
                $site_visit->otp_no = $otp_no;

                $site_visit_estimates = OnSiteOrderEstimate::where('on_site_order_id', $site_visit->id)->count();

                //Type 1 -> Estimate
                //Type 2 -> Revised Estimate
                $type = 1;
                if ($site_visit_estimates > 1) {
                    $type = 2;
                }

                //Generate PDF
                $generate_on_site_estimate_pdf = OnSiteOrder::generateEstimatePDF($site_visit->id, $type);

                $url = url('/') . '/on-site-visit/estimate/customer/view/' . $site_visit->id . '/' . $otp_no;
                $short_url = ShortUrl::createShortLink($url, $maxlength = "7");

                $message = 'Dear Customer, Kindly click on this link to approve for TVS job order ' . $short_url;

                if (!$site_visit->customer) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Not Found!'],
                    ]);
                }

                $customer_mobile = $site_visit->customer->mobile_no;

                if (!$customer_mobile) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Mobile Number Not Found!'],
                    ]);
                }

                $msg = sendSMSNotification($customer_mobile, $message);

                //Update OnSiteOrder Estimate
                $on_site_order_estimate = OnSiteOrderEstimate::where('on_site_order_id', $site_visit->id)->orderBy('id', 'DESC')->first();
                $on_site_order_estimate->status_id = 10071;
                $on_site_order_estimate->updated_by_id = Auth::user()->id;
                $on_site_order_estimate->updated_at = Carbon::now();
                $on_site_order_estimate->save();

                $message = 'Estimation sent to customer successfully!';

            }
            //Work completed
            elseif ($request->type_id == 4) {
                $site_visit->status_id = 9;
                $message = 'On Site Visit Work Completed Successfully!';
            }
            //Send sms to customer for payment
            elseif ($request->type_id == 5) {
                $site_visit->status_id = 10;

                OnSiteOrderRepairOrder::where('on_site_order_id', $site_visit->id)->where('status_id', 8183)->whereNull('removal_reason_id')->update(['status_id' => 8187, 'updated_at' => Carbon::now()]);

                $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 2)->whereNull('end_date_time')->first();
                if ($travel_log) {
                    $travel_log->end_date_time = Carbon::now();
                    $travel_log->updated_by_id = Auth::user()->id;
                    $travel_log->updated_at = Carbon::now();
                    $travel_log->save();
                }

                //Generate Labour PDF
                $generate_on_site_estimate_pdf = OnSiteOrder::generateLabourPDF($site_visit->id);

                //Generate Part PDF
                $generate_on_site_estimate_pdf = OnSiteOrder::generatePartPDF($site_visit->id);

                //Generate Bill Details PDF
                $generate_on_site_estimate_pdf = OnSiteOrder::generateEstimatePDF($site_visit->id, $type = 3);

                $otp_no = mt_rand(111111, 999999);
                $site_visit->otp_no = $otp_no;

                $url = url('/') . '/on-site-visit/view/bill-details/' . $site_visit->id . '/' . $otp_no;
                $short_url = ShortUrl::createShortLink($url, $maxlength = "7");

                $message = 'Dear Customer, Kindly click on this link to pay for the TVS job order ' . $short_url . ' Job Card Number : ' . $site_visit->job_card_number . ' - TVS';

                if (!$site_visit->customer) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Not Found!'],
                    ]);
                }

                $customer_mobile = $site_visit->customer->mobile_no;

                if (!$customer_mobile) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => ['Customer Mobile Number Not Found!'],
                    ]);
                }

                $msg = sendSMSNotification($customer_mobile, $message);

                $message = 'On Site Visit Completed Successfully!';
            } else {
                // $site_visit->status_id = 8;
                $message = 'On Site Visit Updated Successfully!';
            }

            $site_visit->updated_by_id = Auth::user()->id;
            $site_visit->updated_at = Carbon::now();
            $site_visit->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    public function getTimeLog(Request $request)
    {
        // dd($request->all());
        try {
            $site_visit = OnSiteOrder::with([
                'onSiteOrderTravelLogs',
                'onSiteOrderWorkLogs',
            ])->find($request->id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'On Site Visit Not Found!',
                    ],
                ]);
            }

            // dd($site_visit);
            //Get Travel Time Log
            if (count($site_visit->onSiteOrderTravelLogs) > 0) {
                $duration = array();
                foreach ($site_visit->onSiteOrderTravelLogs as $on_site_travel_log) {
                    if ($on_site_travel_log['end_date_time']) {
                        $time1 = strtotime($on_site_travel_log['start_date_time']);
                        $time2 = strtotime($on_site_travel_log['end_date_time']);
                        if ($time2 < $time1) {
                            $time2 += 86400;
                        }

                        $total_duration = date("H:i:s", strtotime("00:00") + ($time2 - $time1));
                        $duration[] = $total_duration;

                        $format_change = explode(':', $total_duration);
                        $hour = $format_change[0];
                        $minutes = $format_change[1];

                        $total_duration_in_hrs = $hour . ' hrs ' . $minutes . ' min';

                        $on_site_travel_log->total_duration = $total_duration_in_hrs;
                    }
                }

                $total_duration = sum_mechanic_duration($duration);
                $format_change = explode(':', $total_duration);

                $hour = $format_change[0];
                $minutes = $format_change[1];

                $total_travel_hours = $hour . ' hrs ' . $minutes . ' min';
                unset($duration);

            } else {
                $total_travel_hours = '-';
            }

            //Get Work Time Log
            if (count($site_visit->onSiteOrderWorkLogs) > 0) {
                $work_duration = array();
                foreach ($site_visit->onSiteOrderWorkLogs as $on_site_work_log) {
                    if ($on_site_work_log['end_date_time']) {
                        $time1 = strtotime($on_site_work_log['start_date_time']);
                        $time2 = strtotime($on_site_work_log['end_date_time']);
                        if ($time2 < $time1) {
                            $time2 += 86400;
                        }

                        $total_duration = date("H:i:s", strtotime("00:00") + ($time2 - $time1));
                        $work_duration[] = $total_duration;

                        $format_change = explode(':', $total_duration);
                        $hour = $format_change[0];
                        $minutes = $format_change[1];

                        $total_duration_in_hrs = $hour . ' hrs ' . $minutes . ' min';

                        $on_site_work_log->total_duration = $total_duration_in_hrs;
                    }
                }
                // dd($work_duration);
                $total_duration = sum_mechanic_duration($work_duration);
                $format_change = explode(':', $total_duration);

                $hour = $format_change[0];
                $minutes = $format_change[1];

                $total_work_hours = $hour . ' hrs ' . $minutes . ' min';
                unset($work_duration);

            } else {
                $total_work_hours = '-';
            }

            $travel_start_button_status = 'true';
            $travel_end_button_status = 'false';

            $work_start_button_status = 'false';
            $work_end_button_status = 'false';

            //Travel Log Start Button Status
            $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 1)->whereNull('end_date_time')->first();
            if ($travel_log) {
                $travel_start_button_status = 'false';
                $travel_end_button_status = 'true';

                $work_start_button_status = 'true';
                $work_end_button_status = 'false';
            }

            $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 2)->whereNull('end_date_time')->first();
            if ($travel_log) {
                $travel_start_button_status = 'false';
                $travel_end_button_status = 'false';

                $work_start_button_status = 'false';
                $work_end_button_status = 'true';
            }

            return response()->json([
                'success' => true,
                'travel_logs' => $site_visit->onSiteOrderTravelLogs,
                'work_logs' => $site_visit->onSiteOrderWorkLogs,
                'total_travel_hours' => $total_travel_hours,
                'total_work_hours' => $total_work_hours,
                'travel_start_button_status' => $travel_start_button_status,
                'travel_end_button_status' => $travel_end_button_status,
                'work_start_button_status' => $work_start_button_status,
                'work_end_button_status' => $work_end_button_status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    public function saveTimeLog(Request $request)
    {
        // dd($request->all());
        try {
            $site_visit = OnSiteOrder::find($request->on_site_order_id);

            if (!$site_visit) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation Error',
                    'errors' => [
                        'Site Visit Not Found!',
                    ],
                ]);
            }

            DB::beginTransaction();

            if ($request->work_log_type == 'travel_log') {
                if ($request->type_id == 1) {

                    //Check alreay save or not not means site visit status update
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 1)->first();
                    if (!$travel_log) {
                        $site_visit->status_id = 11;
                        $site_visit->updated_by_id = Auth::user()->id;
                        $site_visit->updated_at = Carbon::now();
                        $site_visit->save();
                    }

                    //Check Previous entry closed or not
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 1)->whereNull('end_date_time')->first();
                    if ($travel_log) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Previous Travel Log not closed!',
                            ],
                        ]);
                    }
                    $travel_log = new OnSiteOrderTimeLog;
                    $travel_log->on_site_order_id = $site_visit->id;
                    $travel_log->work_log_type_id = 1;
                    $travel_log->start_date_time = Carbon::now();
                    $travel_log->created_by_id = Auth::user()->id;
                    $travel_log->created_at = Carbon::now();
                    $travel_log->save();
                    $message = 'Travel Log Added Successfully!';
                } else {
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 1)->whereNull('end_date_time')->first();
                    if (!$travel_log) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Previous Travel Log not found!',
                            ],
                        ]);
                    }
                    $travel_log->end_date_time = Carbon::now();
                    $travel_log->updated_by_id = Auth::user()->id;
                    $travel_log->updated_at = Carbon::now();
                    $travel_log->save();
                    $message = 'Travel Log Updated Successfully!';
                }
            } else {
                if ($request->type_id == 1) {

                    //Check already save or not not means site visit status update
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 2)->first();

                    // if (!$travel_log) {
                    $site_visit->status_id = 14;
                    $site_visit->updated_by_id = Auth::user()->id;
                    $site_visit->updated_at = Carbon::now();
                    $site_visit->save();
                    // }

                    //Check Previous entry closed or not
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 2)->whereNull('end_date_time')->first();
                    if ($travel_log) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Previous Work Log not closed!',
                            ],
                        ]);
                    }
                    $travel_log = new OnSiteOrderTimeLog;
                    $travel_log->on_site_order_id = $site_visit->id;
                    $travel_log->work_log_type_id = 2;
                    $travel_log->start_date_time = Carbon::now();
                    $travel_log->created_by_id = Auth::user()->id;
                    $travel_log->created_at = Carbon::now();
                    $travel_log->save();
                    $message = 'Work Started Successfully!';
                } else {
                    $travel_log = OnSiteOrderTimeLog::where('on_site_order_id', $site_visit->id)->where('work_log_type_id', 2)->whereNull('end_date_time')->first();
                    if (!$travel_log) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation Error',
                            'errors' => [
                                'Previous Work Log not found!',
                            ],
                        ]);
                    }

                    $site_visit->status_id = 15;
                    $site_visit->updated_by_id = Auth::user()->id;
                    $site_visit->updated_at = Carbon::now();
                    $site_visit->save();

                    $travel_log->end_date_time = Carbon::now();
                    $travel_log->updated_by_id = Auth::user()->id;
                    $travel_log->updated_at = Carbon::now();
                    $travel_log->save();
                    $message = 'Work Stopped Successfully!';
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Network Down!',
                'errors' => ['Exception Error' => $e->getMessage()],
            ]);
        }
    }

    public function deleteLabourParts(Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            if ($request->payable_type == 'labour') {
                $validator = Validator::make($request->all(), [
                    'labour_parts_id' => [
                        'required',
                        'integer',
                        'exists:on_site_order_repair_orders,id',
                    ],
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                if ($request->removal_reason_id == 10022) {
                    $on_site_order_repair_order = OnSiteOrderRepairOrder::find($request->labour_parts_id);
                    if ($on_site_order_repair_order) {
                        $on_site_order_repair_order->removal_reason_id = $request->removal_reason_id;
                        $on_site_order_repair_order->removal_reason = $request->removal_reason;
                        $on_site_order_repair_order->updated_by_id = Auth::user()->id;
                        $on_site_order_repair_order->updated_at = Carbon::now();
                        $on_site_order_repair_order->save();
                    }
                } else {
                    $on_site_order_repair_order = OnSiteOrderRepairOrder::where('id', $request->labour_parts_id)->forceDelete();
                }

            } else {
                $validator = Validator::make($request->all(), [
                    'labour_parts_id' => [
                        'required',
                        'integer',
                        'exists:on_site_order_parts,id',
                    ],
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Validation Error',
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                if ($request->removal_reason_id == 10022) {
                    $on_site_order_parts = OnSiteOrderPart::find($request->labour_parts_id);
                    if ($on_site_order_parts) {
                        $on_site_order_parts->removal_reason_id = $request->removal_reason_id;
                        $on_site_order_parts->removal_reason = $request->removal_reason;
                        $on_site_order_parts->updated_by_id = Auth::user()->id;
                        $on_site_order_parts->updated_at = Carbon::now();
                        $on_site_order_parts->save();
                    }
                } else {
                    $on_site_order_parts = OnSiteOrderPart::where('id', $request->labour_parts_id)->forceDelete();
                }
            }

            DB::commit();
            if ($request->payable_type == 'labour') {
                return response()->json([
                    'success' => true,
                    'message' => 'Labour Deleted Successfully',
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Part Deleted Successfully',
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Server Error!',
                'errors' => [
                    'Error : ' . $e->getMessage() . '. Line : ' . $e->getLine() . '. File : ' . $e->getFile(),
                ],
            ]);
        }
    }

    //Get Repair Orders
    public function getRepairOrderSearchList(Request $request)
    {
        return RepairOrder::searchRepairOrder($request);
    }
}
