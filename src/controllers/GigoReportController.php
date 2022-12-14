<?php

namespace Abs\GigoPkg;
use App\City;
use App\Config;
use App\Customer;
use App\Http\Controllers\Controller;
use App\JobOrder;
use App\OutletShift;
use App\JobCard;
use App\GateLog;
use App\GatePass;
use App\EmployeeShift;
use App\Employee;
use App\Part;
use App\AttendanceLog;
use App\VehicleModel;
use App\RepairOrderMechanic;
use App\MechanicTimeLog;
use App\JobOrderRepairOrder;
use Auth;
use DB;
use Entrust;
use Excel;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class GigoReportController extends Controller {

	public function __construct() {
		$this->data['theme'] = config('custom.theme');
	}

	 public function mechanicWorkLogExportOld(Request $request) {
	 	// dd($request->all());
        try {

            ini_set('max_execution_time', 0);
			ini_set('memory_limit', -1);

            if ($request->export_date) {
                $date_range = explode(' to ', $request->export_date);
                $start_date = $date_range[0];    
                $end_date = $date_range[1];
            } else {
                $start_date = date('01-m-Y');
                $end_date = date('t-m-Y');
            }

            $employees = Employee::select([
                'users.id',
                'employees.code as employee_code',
                'users.name as employee_name',
                'employees.outlet_id',
                // 'employees.shift_id',
                'employees.id as employee_id'
            ])
                ->join('users', 'users.entity_id', 'employees.id')
                ->join('outlets', 'outlets.id', 'employees.outlet_id')
                ->where('employees.is_mechanic', 1)
                ->where('users.user_type_id', 1) //EMPLOYEE
                ->where('employees.outlet_id', 110)
                // ->where('employees.outlet_id', Auth::user()->working_outlet_id)
                // ->whereIn('users.id', [191,192,194]) //EMPLOYEE
                ->orderBy('users.name', 'asc')
            ->get();
            
            // dd($employees);
            $summary_details = array();
            $work_logs_details = array();
            
            if($employees){
                foreach($employees as $key => $employee){
                    // dd($employee);
                    $start = strtotime($start_date);
		            $end = strtotime($end_date);
                    $overall_lunch_hours = []; 
                    $overall_work_hours = []; 
                    $overall_employee_work_hours = []; 
                    $overall_idle_hours = []; 
                    while (date('Y-m-d', $start) <= date('Y-m-d', $end)) {
                        // dump(date('Y-m-d', $start));
                        // dump(date("l",$start));
                        // dump($employee->id);
                        $summary_detail['date'] = date('d-m-Y', $start);
                        $summary_detail['employee_code'] = $employee->employee_code;
                        $summary_detail['employee_name'] = $employee->employee_name;
                        
                        $summary_detail['total_hours'] = '';
                        $summary_detail['working_hours'] = '';
                        $summary_detail['idle_hours'] = '';
                        
                        //Get Employye SHift
                        $employee_shift = EmployeeShift::join('shifts','shifts.id','employee_shifts.shift_id')->where('date',date('Y-m-d', $start))->where('employee_id',$employee->employee_id)->select('shifts.name as shift_name','employee_shifts.shift_id')->first();
                        
                        $lunch_hour = '00:00:00';
                        $total_working_hours = '00:00:00';

                        $punch_in_time = AttendanceLog::where('user_id',$employee->id)->whereDate('attendance_logs.date',date('Y-m-d', $start))->pluck('in_time')->first();
                        $summary_detail['total_hours'] = '00.00';
                        if($employee_shift){
                            //Outlet Shift
                            if(date("l",$start) == 'Sunday'){
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12282)->first();
                            }elseif(date("l",$start) == 'Saturday'){
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12281)->first();
                            }else{
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12280)->first();
                            }

                            $outlet_shift_lunch_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12283)->first(); 
                            
                            if($outlet_shift_lunch_hours){
                                $array1 = explode(':', $outlet_shift_lunch_hours->start_time);
                                $array2 = explode(':', $outlet_shift_lunch_hours->end_time);
                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;

                                $lunch_hour = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';

                                $overall_lunch_hours[] = $lunch_hour;
                            }

                            if($outlet_working_hours){
                                $array1 = explode(':', $outlet_working_hours->start_time);
                                $array2 = explode(':', $outlet_working_hours->end_time);
                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
								if ($diff > 0) {
									$total_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
									$summary_detail['total_hours'] = intdiv($diff, 60) . '.' . ($diff % 60);
								} elseif ($diff == 0) {
								} else {
                                    $to_time = strtotime("2021-08-07 ".$outlet_working_hours->end_time);
                                    $from_time = strtotime("2021-08-06 ".$outlet_working_hours->start_time);
									$diff = round(abs($to_time - $from_time) / 60,2);

									$total_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
									$summary_detail['total_hours'] = intdiv($diff, 60) . '.' . ($diff % 60);
								}
                                $overall_work_hours[] = $total_working_hours;
                            }
                        }
                        // dd($summary_detail);

                        //Get Mechanic Worklog
                        $mechanic_time_logs  = MechanicTimeLog::join('repair_order_mechanics','repair_order_mechanics.id','mechanic_time_logs.repair_order_mechanic_id')
                        ->join('job_order_repair_orders','job_order_repair_orders.id','repair_order_mechanics.job_order_repair_order_id')
                        ->join('repair_orders','repair_orders.id','job_order_repair_orders.repair_order_id')
                        ->join('job_orders','job_orders.id','job_order_repair_orders.job_order_id')
                        ->join('vehicles','vehicles.id','job_orders.vehicle_id')
                        ->where('repair_order_mechanics.mechanic_id',$employee->id)
                        ->whereDate('mechanic_time_logs.start_date_time',date('Y-m-d', $start))
                        // ->whereNotNull('mechanic_time_logs.end_date_time')
                        ->select('mechanic_time_logs.start_date_time','mechanic_time_logs.end_date_time','repair_order_mechanics.job_order_repair_order_id',
                        'repair_orders.code','repair_orders.name','job_orders.job_card_number','vehicles.registration_number','mechanic_time_logs.status_id','mechanic_time_logs.id'
                        )
                        ->get();
                        
                        // dd($mechanic_time_logs);

                        $employee_work_hour = '00:00:00';

						//Employee Detailed Report
						$work_logs_detail = array();

                        if(count($mechanic_time_logs) > 0){
                            $duration_difference = []; 
                            $lunch_difference = []; 
							$duration = [];
							// dump($mechanic_time_logs);
                            foreach($mechanic_time_logs as $mechanic_time_log){
                                // dd($mechanic_time_log);
								$work_logs_detail['date'] = date('d-m-Y', $start);
								$work_logs_detail['employee_code'] = $employee->employee_code;
								$work_logs_detail['employee_name'] = $employee->employee_name;
								$work_logs_detail['shift'] = $employee_shift ? $employee_shift->shift_name : '';
								$work_logs_detail['punch_in_time'] = $punch_in_time;
								$work_logs_detail['rot_code'] = $mechanic_time_log->code;
								$work_logs_detail['rot_name'] = $mechanic_time_log->name;
								$work_logs_detail['job_card_number'] = $mechanic_time_log->job_card_number;
								$work_logs_detail['reg_number'] = $mechanic_time_log->registration_number;
                                $work_logs_detail['start_time'] = date('h:i', strtotime($mechanic_time_log->start_date_time));
                                $work_logs_detail['end_time'] = $mechanic_time_log->end_date_time ? date('h:i', strtotime($mechanic_time_log->end_date_time)) : '';
                                // $work_logs_detail['idle_hours'] = ;

                                // dd($work_logs_detail);
                                if($mechanic_time_log->end_date_time){
                                    $time1 = strtotime($mechanic_time_log->start_date_time);
                                    $time2 = strtotime($mechanic_time_log->end_date_time);
                                    if ($time2 < $time1) {
                                        $time2 += 86400;
                                    }

                                    if($mechanic_time_log->status_id != 8265){
                                        //TIME DURATION DIFFERENCE PARTICULAR MECHANIC DURATION
                                        $duration_difference[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                        //TOTAL DURATION FOR PARTICLUAR EMPLOEE
                                        $duration[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                        //OVERALL TOTAL WORKING DURATION
                                        $overall_total_duration[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                        $total_hours_worked = sum_mechanic_duration($duration_difference);

                                        $time_format_change = explode(':', $total_hours_worked);
                                        $worklog_hour = $time_format_change[0];
                                        $worklog_minute = $time_format_change[1];
                                        $worklog_second = $time_format_change[2];
                                    }else{
                                        $lunch_difference[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));
                                        $total_hours_worked = sum_mechanic_duration($lunch_difference);

                                        $time_format_change = explode(':', $total_hours_worked);
                                        $worklog_hour = $time_format_change[0];
                                        $worklog_minute = $time_format_change[1];
                                        $worklog_second = $time_format_change[2];
                                    }

                                    $work_logs_detail['rot_hours'] = $worklog_hour . '.' . $worklog_minute;
                                    unset($duration_difference);

                                    $work_logs_detail['remarks'] = $mechanic_time_log->status_id == 8265 ? 'Lunch' : 'Work Hours';

									$work_logs_details[] = $work_logs_detail;
                                }else{
                                    $work_logs_detail['rot_hours'] = '';
                                    $work_logs_detail['remarks'] = 'Work InProgress';

									$work_logs_details[] = $work_logs_detail;
                                }
                            }

                            //TOTAL WORKING HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($duration);
                            $total_duration = date("H:i:s", strtotime($total_duration));
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];

                            $employee_work_hour = $hour . ':' . $minutes . ':' . $seconds;
                            $overall_employee_work_hours[] = $employee_work_hour;
                            $summary_detail['working_hours'] = $hour . '.' . $minutes;
                            
                            unset($duration);

                            // //Add Working & Lunch Hours
                            // $array1 = explode(':', $employee_work_hour);
                            // $array2 = explode(':', $lunch_hour);

                            // $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                            // $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                            // $diff = $minutes2 + $minutes1;

                            // $total_employee_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';

                            if($total_working_hours != '00:00:00'){
                                //Find Total Idle Hours
                                $array1 = explode(':', $employee_work_hour);
                                $array2 = explode(':', $total_working_hours);

                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
                                // $total_idle_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                $total_idle_hours = intdiv($diff, 60) . '.' . ($diff % 60);
                                $summary_detail['idle_hours'] = $total_idle_hours;
                                $overall_idle_hours[] = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';;
                            }
                        }else{
							$work_logs_detail['date'] = date('d-m-Y', $start);
							$work_logs_detail['employee_code'] = $employee->employee_code;
							$work_logs_detail['employee_name'] = $employee->employee_name;
							$work_logs_detail['shift'] = $employee_shift ? $employee_shift->shift_name : '';
							$work_logs_detail['punch_in_time'] = $punch_in_time;
							$work_logs_detail['rot_code'] = '';
							$work_logs_detail['rot_name'] = '';
							$work_logs_detail['job_card_number'] = '';
							$work_logs_detail['reg_number'] = '';
							$work_logs_detail['start_time'] = '';
							$work_logs_detail['end_time'] = '';
							// $work_logs_detail['idle_hours'] = ;
							$work_logs_detail['rot_hours'] = '';
							$work_logs_detail['remarks'] = '';
							$work_logs_details[] = $work_logs_detail;
                            
                            $summary_detail['working_hours'] = '00.00';
                            $summary_detail['idle_hours'] = $summary_detail['total_hours'];

                            if($total_working_hours != '00:00:00'){
                                //Find Total Idle Hours
                                $array1 = explode(':', $employee_work_hour);
                                $array2 = explode(':', $total_working_hours);
                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
                                // $total_idle_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                $total_idle_hours = intdiv($diff, 60) . '.' . ($diff % 60);
                                $summary_detail['idle_hours'] = $total_idle_hours;
                                $overall_idle_hours[] = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';;
                            }
						}

                        $summary_details[] = $summary_detail;

                        //Add Employees Overall Total
                        if(date('Y-m-d', $start) == date('Y-m-d', $end)){
                            $summary_detail = [];
                            $summary_detail['date'] = 'Grand Total';
                            $summary_detail['employee_code'] = '';
                            $summary_detail['employee_name'] = '';                            

                            //TOTAL OVERALL HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_work_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['total_hours'] = $hour . '.' . $minutes;

                            //TOTAL OVERALL WORKING HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_employee_work_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['working_hours'] = $hour . '.' . $minutes;

                            //TOTAL OVERALL IDLE HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_idle_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['idle_hours'] = $hour . '.' . $minutes;

                            $summary_details[] = $summary_detail;
                        }

                        $start = strtotime("+1 day", $start);
                    }
                }
                // dd();
            }

            // dump('.....');
            // dd($work_logs_details);
            // dd($summary_details);

        	ob_end_clean();
			ob_start();

            $summary_header = [
				'Date',
				'Employee Code',
				'Employee Name',
				'Total Hours',
				'Working Hours',
				'Idle Hours',
			];
		
			$detail_worklog_header = [
				'Date',
				'Employee Code',
				'Employee Name',
				'Shift',
				'PunchIn Time',
				'ROT Code',
				'ROT Name',
				'JobCard Number',
				'Registration Number',
				'Start Time',
				'End Time',
				'ROT Hours',
				'Remarks',
			];

			$time_stamp = date('Y_m_d_h_i_s');
			Excel::create('Mechanic Report - ' . $time_stamp, function ($excel) use ($summary_header, $detail_worklog_header, $summary_details, $work_logs_details) {
				$excel->sheet('Summary', function ($sheet) use ($summary_header, $summary_details) {
					$sheet->fromArray($summary_details, null, 'A1');
					$sheet->row(1, $summary_header);
					$sheet->row(1, function ($row) {
						$row->setBackground('#bbc0c9');
						$row->setAlignment('center');
						$row->setFontSize(10);
						$row->setFontFamily('Work Sans');
						$row->setFontWeight('bold');
					});
					$sheet->cell('A:F', function ($row) {
						$row->setAlignment('center');
						$row->setFontFamily('Work Sans');
						$row->setFontSize(10);
					});
					$sheet->setAutoSize(true);
				});

				$excel->sheet('Detailed Report', function ($sheet) use ($detail_worklog_header, $work_logs_details) {
					$sheet->fromArray($work_logs_details, null, 'A1');
					$sheet->row(1, $detail_worklog_header);
					$sheet->row(1, function ($row) {
						$row->setBackground('#bbc0c9');
						$row->setAlignment('center');
						$row->setFontSize(10);
						$row->setFontFamily('Work Sans');
						$row->setFontWeight('bold');
					});
					$sheet->cell('A:F', function ($row) {
						$row->setAlignment('center');
						$row->setFontFamily('Work Sans');
						$row->setFontSize(10);
					});
					$sheet->setAutoSize(true);
				});
			})->export('xlsx');

        } catch (Exception $e) {
            print_r($e);
        }
    }

    //BY KARTHICK R
    public function mechanicWorkLogExport(Request $request) {
        // dd($request->all());
        try {

            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if ($request->export_date) {
                $date_range = explode(' to ', $request->export_date);
                $start_date = $date_range[0];    
                $end_date = $date_range[1];
            } else {
                $start_date = date('01-m-Y');
                $end_date = date('t-m-Y');
            }

            $employees = Employee::select([
                'users.id',
                'employees.code as employee_code',
                'users.name as employee_name',
                'employees.outlet_id',
                // 'employees.shift_id',
                'employees.id as employee_id',
                'outlets.code as employee_outlet_code',
                'outlets.name as employee_outlet_name',
                'outlets.ax_name as employee_outlet_ax_name',
            ])
                ->join('users', 'users.entity_id', 'employees.id')
                ->join('outlets', 'outlets.id', 'employees.outlet_id')
                ->where('employees.is_mechanic', 1)
                ->where('users.user_type_id', 1) //EMPLOYEE
                // ->where('employees.outlet_id', Auth::user()->working_outlet_id)
                ->orderBy('users.name', 'asc');

                if (!Entrust::can('gigo-report-all-outlet')) {
                    if (Entrust::can('gigo-report-mapped-outlet')) {
                        $outlet_ids = Auth::user()->employee->outlets->pluck('id')->toArray();
                        array_push($outlet_ids, Auth::user()->employee->outlet_id);
                        $employees->whereIn('employees.outlet_id', $outlet_ids);
                    }else {
                        $employees->where('employees.outlet_id', Auth::user()->working_outlet_id);
                    }
                }
            $employees = $employees->get();
            
            // dd($employees);
            $summary_details = array();
            $work_logs_details = array();
            
            if($employees){
                foreach($employees as $key => $employee){
                    // dd($employee);
                    $start = strtotime($start_date);
                    $end = strtotime($end_date);
                    $overall_lunch_hours = []; 
                    $overall_work_hours = []; 
                    $overall_employee_work_hours = []; 
                    $overall_idle_hours = []; 
                    while (date('Y-m-d', $start) <= date('Y-m-d', $end)) {
                        // dump(date('Y-m-d', $start));
                        // dump(date("l",$start));
                        // dump($employee->id);
                        //Get Employye SHift
                        $employee_shift = EmployeeShift::join('shifts','shifts.id','employee_shifts.shift_id')->where('date',date('Y-m-d', $start))->where('employee_id',$employee->employee_id)->select('shifts.name as shift_name','employee_shifts.shift_id')->first();
                        $punch_in_time = AttendanceLog::where('user_id',$employee->id)->whereDate('attendance_logs.date',date('Y-m-d', $start))->orderBy('id','asc')->pluck('in_time')->first();

                        $punch_out_time = AttendanceLog::where('user_id', $employee->id)->whereDate('attendance_logs.date',date('Y-m-d', $start))->orderBy('id','desc')->pluck('out_time')->first();

                        $summary_detail['outlet_code'] = $employee->employee_outlet_code;
                        $summary_detail['outlet_name'] = $employee->employee_outlet_ax_name ? $employee->employee_outlet_ax_name : $employee->employee_outlet_name;
                        $summary_detail['date'] = date('d-m-Y', $start);
                        $summary_detail['employee_code'] = $employee->employee_code;
                        $summary_detail['employee_name'] = $employee->employee_name;
                        $summary_detail['shift'] = $employee_shift ? $employee_shift->shift_name : '';
                        $summary_detail['punch_in_time'] = $punch_in_time;
                        $summary_detail['punch_out_time'] = $punch_out_time;
                        $summary_detail['total_hours'] = '';
                        $summary_detail['working_hours'] = '';
                        $summary_detail['lunch_hours'] = '';
                        $summary_detail['idle_hours'] = '';
                        
                        $lunch_hour = '00:00:00';
                        $total_working_hours = '00:00:00';
                        
                        $summary_detail['total_hours'] = '00.00';
                        if($employee_shift){
                            //Outlet Shift
                            if(date("l",$start) == 'Sunday'){
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12282)->first();
                            }elseif(date("l",$start) == 'Saturday'){
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12281)->first();
                            }else{
                                $outlet_working_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12280)->first();
                            }

                            // $outlet_shift_lunch_hours = OutletShift::where('shift_id',$employee_shift->shift_id)->where('outlet_id',$employee->outlet_id)->where('shift_type_id',12283)->first(); 
                            
                            // if($outlet_shift_lunch_hours){
                            //     $array1 = explode(':', $outlet_shift_lunch_hours->start_time);
                            //     $array2 = explode(':', $outlet_shift_lunch_hours->end_time);
                            //     $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                            //     $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                            //     $diff = $minutes2 - $minutes1;

                            //     $lunch_hour = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                            //     // $summary_detail['lunch_hours'] = $lunch_hour;

                            //     $overall_lunch_hours[] = $lunch_hour;

                            //     $lunch_hour = intdiv($diff, 60) . '.' . ($diff % 60);
                            //     $summary_detail['lunch_hours'] = $lunch_hour;

                            // }

                            if($outlet_working_hours && $punch_in_time){
                                $array1 = explode(':', $outlet_working_hours->start_time);
                                $array2 = explode(':', $outlet_working_hours->end_time);
                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
                                // dump($diff);
                                if ($diff > 0) {
                                    $total_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                    $summary_detail['total_hours'] = sprintf("%02d", intdiv($diff, 60)) . '.' . (sprintf("%02d", $diff % 60));

                                } elseif ($diff == 0) {
                                } else {
                                    $to_time = strtotime("2021-08-07 ".$outlet_working_hours->end_time);
                                    $from_time = strtotime("2021-08-06 ".$outlet_working_hours->start_time);
                                    $diff = round(abs($to_time - $from_time) / 60,2);

                                    $total_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                    // $summary_detail['total_hours'] = intdiv($diff, 60) . '.' . ($diff % 60);
                                    $summary_detail['total_hours'] = sprintf("%02d", intdiv($diff, 60)) . '.' . (sprintf("%02d", $diff % 60));
                                                            }
                                    $overall_work_hours[] = $total_working_hours;
                            }
                        }
                        // dd($summary_detail);

                        //Get Mechanic Worklog
                        $mechanic_time_logs  = MechanicTimeLog::join('repair_order_mechanics','repair_order_mechanics.id','mechanic_time_logs.repair_order_mechanic_id')
                        ->join('job_order_repair_orders','job_order_repair_orders.id','repair_order_mechanics.job_order_repair_order_id')
                        ->join('repair_orders','repair_orders.id','job_order_repair_orders.repair_order_id')
                        ->join('job_orders','job_orders.id','job_order_repair_orders.job_order_id')
                        ->join('vehicles','vehicles.id','job_orders.vehicle_id')
                        ->where('repair_order_mechanics.mechanic_id',$employee->id)
                        ->whereDate('mechanic_time_logs.start_date_time',date('Y-m-d', $start))
                        // ->whereNotNull('mechanic_time_logs.end_date_time')
                        ->select('mechanic_time_logs.start_date_time','mechanic_time_logs.end_date_time','repair_order_mechanics.job_order_repair_order_id',
                        'repair_orders.code','repair_orders.name','repair_orders.hours','job_orders.job_card_number','vehicles.registration_number','mechanic_time_logs.status_id','mechanic_time_logs.id'
                        )
                        ->get();
                        
                        // dd($mechanic_time_logs);

                        $employee_work_hour = '00:00:00';
                        $total_employee_working_hours = '00:00:00';

                        //Employee Detailed Report
                        $work_logs_detail = array();

                        if(count($mechanic_time_logs) > 0){
                            $duration_difference = []; 
                            $lunch_difference = []; 
                            $duration = [];
                            $lunch_hour = '00:00:00';
                            foreach($mechanic_time_logs as $mechanic_time_log){
                                if($mechanic_time_log->start_date_time != $mechanic_time_log->end_date_time){
                                    $work_logs_detail['outlet_code'] = $employee->employee_outlet_code;
                                    $work_logs_detail['outlet_name'] = $employee->employee_outlet_ax_name ? $employee->employee_outlet_ax_name : $employee->employee_outlet_name;
                                    $work_logs_detail['date'] = date('d-m-Y', $start);
                                    $work_logs_detail['employee_code'] = $employee->employee_code;
                                    $work_logs_detail['employee_name'] = $employee->employee_name;
                                    
                                    $work_logs_detail['rot_code'] = $mechanic_time_log->code;
                                    $work_logs_detail['rot_name'] = $mechanic_time_log->name;
                                    $work_logs_detail['actual_rot_hours'] = $mechanic_time_log->hours;
                                    $work_logs_detail['job_card_number'] = $mechanic_time_log->job_card_number;
                                    $work_logs_detail['reg_number'] = $mechanic_time_log->registration_number;
                                    $work_logs_detail['start_time'] = date('h:i', strtotime($mechanic_time_log->start_date_time));
                                    $work_logs_detail['end_time'] = $mechanic_time_log->end_date_time ? date('h:i', strtotime($mechanic_time_log->end_date_time)) : '';
                                    // $work_logs_detail['idle_hours'] = ;

                                    // dd($work_logs_detail);
                                    if($mechanic_time_log->end_date_time){
                                        $time1 = strtotime($mechanic_time_log->start_date_time);
                                        $time2 = strtotime($mechanic_time_log->end_date_time);
                                        if ($time2 < $time1) {
                                            $time2 += 86400;
                                        }

                                        if($mechanic_time_log->status_id != 8265){
                                            //TIME DURATION DIFFERENCE PARTICULAR MECHANIC DURATION
                                            $duration_difference[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                            //TOTAL DURATION FOR PARTICLUAR EMPLOEE
                                            $duration[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                            //OVERALL TOTAL WORKING DURATION
                                            $overall_total_duration[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));

                                            $total_hours_worked = sum_mechanic_duration($duration_difference);

                                            $time_format_change = explode(':', $total_hours_worked);
                                            $worklog_hour = $time_format_change[0];
                                            $worklog_minute = $time_format_change[1];
                                            $worklog_second = $time_format_change[2];
                                        }else{
                                            $lunch_difference[] = date("H:i:s", strtotime("00:00") + ($time2 - $time1));
                                            $total_hours_worked = sum_mechanic_duration($lunch_difference);

                                            $time_format_change = explode(':', $total_hours_worked);
                                            $worklog_hour = $time_format_change[0];
                                            $worklog_minute = $time_format_change[1];
                                            $worklog_second = $time_format_change[2];

                                            $lunch_hour = $total_hours_worked;
                                            $overall_lunch_hours[] = $lunch_hour;
                                            $summary_detail['lunch_hours'] = $worklog_hour . '.' . $worklog_minute;
                                        }

                                        $work_logs_detail['rot_hours'] = $worklog_hour . '.' . $worklog_minute;
                                        unset($duration_difference);

                                        $work_logs_detail['remarks'] = $mechanic_time_log->status_id == 8265 ? 'Lunch' : 'Work Hours';

                                        $work_logs_details[] = $work_logs_detail;
                                    }else{
                                        $work_logs_detail['rot_hours'] = '';
                                        $work_logs_detail['remarks'] = 'Work InProgress';

                                        $work_logs_details[] = $work_logs_detail;
                                    }
                                }
                            }

                            // dump($work_logs_detail);

                            //TOTAL WORKING HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($duration);
                            $total_duration = date("H:i:s", strtotime($total_duration));
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];

                            $employee_work_hour = $hour . ':' . $minutes . ':' . $seconds;
                            $overall_employee_work_hours[] = $employee_work_hour;
                            $summary_detail['working_hours'] = $hour . '.' . $minutes;
                            
                            unset($duration);

                            //Add Working & Lunch Hours
                            $array1 = explode(':', $employee_work_hour);
                            $array2 = explode(':', $lunch_hour);

                            $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                            $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                            $diff = $minutes2 + $minutes1;

                            $total_employee_working_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';

                            if($total_working_hours != '00:00:00'){
                                //Find Total Idle Hours
                                // dump($total_employee_working_hours,$total_working_hours);
                                // $array1 = explode(':', $employee_work_hour);
                                $array1 = explode(':', $total_employee_working_hours);
                                $array2 = explode(':', $total_working_hours);

                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
                                // $total_idle_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                $total_idle_hours = intdiv($diff, 60) . '.' . ($diff % 60);

                                $summary_detail['idle_hours'] = $total_idle_hours;
                                $overall_idle_hours[] = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';;
                            }
                        }else{
                            $work_logs_detail['outlet_code'] = $employee->employee_outlet_code;
                            $work_logs_detail['outlet_name'] = $employee->employee_outlet_ax_name ? $employee->employee_outlet_ax_name : $employee->employee_outlet_name;
                            $work_logs_detail['date'] = date('d-m-Y', $start);
                            $work_logs_detail['employee_code'] = $employee->employee_code;
                            $work_logs_detail['employee_name'] = $employee->employee_name;
                            $work_logs_detail['rot_code'] = '';
                            $work_logs_detail['rot_name'] = '';
                            $work_logs_detail['actual_rot_hours'] = '';
                            $work_logs_detail['job_card_number'] = '';
                            $work_logs_detail['reg_number'] = '';
                            $work_logs_detail['start_time'] = '';
                            $work_logs_detail['end_time'] = '';
                            // $work_logs_detail['idle_hours'] = ;
                            $work_logs_detail['rot_hours'] = '';
                            $work_logs_detail['remarks'] = '';
                            $work_logs_details[] = $work_logs_detail;
                            
                            $summary_detail['working_hours'] = '00.00';
                            $summary_detail['idle_hours'] = $summary_detail['total_hours'];

                            if($total_working_hours != '00:00:00'){
                                //Find Total Idle Hours
                                // $array1 = explode(':', $employee_work_hour);
                                $array1 = explode(':', $total_employee_working_hours);
                                $array2 = explode(':', $total_working_hours);
                                $minutes1 = ($array1[0] * 60.0 + $array1[1]);
                                $minutes2 = ($array2[0] * 60.0 + $array2[1]);
                                $diff = $minutes2 - $minutes1;
                                // $total_idle_hours = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';
                                $total_idle_hours = intdiv($diff, 60) . '.' . ($diff % 60);
                                $summary_detail['idle_hours'] = $total_idle_hours;
                                $overall_idle_hours[] = intdiv($diff, 60) . ':' . ($diff % 60) . ':00';;
                            }
                                                }

                        $summary_details[] = $summary_detail;

                        //Add Employees Overall Total
                        if(date('Y-m-d', $start) == date('Y-m-d', $end)){
                            $summary_detail = [];
                            $summary_detail['outlet_code'] = 'Grand Total';
                            $summary_detail['outlet_name'] = '';
                            $summary_detail['date'] = '';
                            $summary_detail['employee_code'] = '';
                            $summary_detail['employee_name'] = '';   
                            $summary_detail['shift'] = '';
                            $summary_detail['punch_in_time'] = '';
                            $summary_detail['punch_out_time'] = '';

                            //TOTAL OVERALL HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_work_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['total_hours'] = $hour . '.' . $minutes;

                            //TOTAL OVERALL WORKING HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_employee_work_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['working_hours'] = $hour . '.' . $minutes;

                            $total_duration = sum_mechanic_duration($overall_lunch_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['lunch_hours'] = $hour . '.' . $minutes;

                            //TOTAL OVERALL IDLE HOURS PER EMPLOYEE
                            $total_duration = sum_mechanic_duration($overall_idle_hours);
                            $format_change = explode(':', $total_duration);
                            $hour = $format_change[0];
                            $minutes = $format_change[1];
                            $seconds = $format_change[2];
                            $summary_detail['idle_hours'] = $hour . '.' . $minutes;

                            $summary_details[] = $summary_detail;
                        }

                        $start = strtotime("+1 day", $start);
                    }
                }
                // dd();
            }
            // dump('.....');
            // dd($work_logs_details);
            // dd($summary_details);

            ob_end_clean();
            ob_start();

            $summary_header = [
                'Outlet Code',
                'Outlet Name',
                'Date',
                'Employee Code',
                'Employee Name',
                'Shift',
                'PunchIn Time',
                'Punch Out Time',
                'Total Hours',
                'Working Hours',
                'Lunch Hours',
                'Idle Hours',
            ];
                
            $detail_worklog_header = [
                'Outlet Code',
                'Outlet Name',
                'Date',
                'Employee Code',
                'Employee Name',
                'ROT Code',
                'ROT Name',
                'ROT Hours',
                'JobCard Number',
                'Registration Number',
                'Start Time',
                'End Time',
                'Total Hours',
                'Remarks',
            ];

            $time_stamp = date('Y_m_d_h_i_s');
            Excel::create('Mechanic Report - ' . $time_stamp, function ($excel) use ($summary_header, $detail_worklog_header, $summary_details, $work_logs_details) {
                $excel->sheet('Summary', function ($sheet) use ($summary_header, $summary_details) {
                    $sheet->freezeFirstRow();
                    $sheet->fromArray($summary_details, null, 'A1');
                    $sheet->row(1, $summary_header);
                    $sheet->setAutoSize(true);
                    $sheet->freezePane("E2");
                });

                $excel->sheet('Detailed Report', function ($sheet) use ($detail_worklog_header, $work_logs_details) 
                {
                    $sheet->freezeFirstRow();
                    $sheet->fromArray($work_logs_details, null, 'A1');
                    $sheet->row(1, $detail_worklog_header);
                    $sheet->setAutoSize(true);
                    $sheet->freezePane("E2");
                });
            })->export('xlsx');

        } catch (Exception $e) {
            print_r($e);
        }
    }

    public function attendanceLogExport(Request $request) {
        // dd($request->all());
        try {

            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if (!empty($request->export_date)) {
                $date_range = explode(' to ', $request->export_date);
                $report_start_date = date('Y-m-d', strtotime(trim($date_range[0])));
                $report_end_date = date('Y-m-d', strtotime(trim($date_range[1])));
            } else {
                $report_start_date = date('Y-m-d', strtotime(date('01-m-Y')));
                $report_end_date = date('Y-m-d', strtotime(date('t-m-Y')));
            }

            $attendance_logs = AttendanceLog::select([
                'employees.id as employee_id',
                'employees.code as employee_code',
                'users.name as employee_name',
                'outlets.code as outlet_code',
                'outlets.name as outlet_name',
                'outlets.ax_name as outlet_ax_name',
                'attendance_logs.in_date_time as punch_in_date',
                'attendance_logs.out_date_time as punch_out_date',
            ])
            ->join('users', 'users.id', 'attendance_logs.user_id')
            ->join('employees', 'employees.id', 'users.entity_id')
            ->join('outlets', 'outlets.id', 'employees.outlet_id')
            ->where('users.user_type_id', 1) //EMPLOYEE
            ->whereBetween('attendance_logs.date', [$report_start_date, $report_end_date])
            ->get();
            
            $attendance_details = array();            
            if($attendance_logs){
                foreach($attendance_logs as $key => $attendance_log){
                    $employee_shift = EmployeeShift::join('shifts','shifts.id','employee_shifts.shift_id')
                        ->whereBetween('date', [$report_start_date, $report_end_date])
                        ->where('employee_id', $attendance_log->employee_id)
                        ->select('shifts.name as shift_name','employee_shifts.shift_id')
                        ->first();
                    $attendance_details[] = [
                        $attendance_log->employee_code,                        
                        $attendance_log->employee_name,                        
                        $attendance_log->outlet_code,                        
                        !empty($attendance_log->outlet_ax_name) ? $attendance_log->outlet_ax_name : $attendance_log->outlet_name,
                        !empty($employee_shift->shift_name) ? $employee_shift->shift_name : '',
                        !empty($attendance_log->punch_in_date) ? date('d-m-Y', strtotime($attendance_log->punch_in_date)) : '',
                        !empty($attendance_log->punch_out_date) ? date('d-m-Y', strtotime($attendance_log->punch_out_date)) : '',
                    ];    
                }
            }

            ob_end_clean();
            ob_start();

            $header = [
                'Employee Code',
                'Employee Name',
                'Outlet Code',
                'Outlet Name',
                'Shift',
                'Punch In Date',
                'Punch Out Date',
            ];

            $time_stamp = date('Y_m_d_h_i_s');
            Excel::create('Attendance Report - ' . $time_stamp, function ($excel) use ($header, $attendance_details) {
                $excel->sheet('Logs', function ($sheet) use ($header, $attendance_details) {
                    $sheet->fromArray($attendance_details, null, 'A1');
                    $sheet->row(1, $header);
                    $sheet->row(1, function ($row) {
                        $row->setBackground('#bbc0c9');
                        $row->setAlignment('center');
                        $row->setFontSize(10);
                        $row->setFontFamily('Work Sans');
                        $row->setFontWeight('bold');
                    });
                    $sheet->cell('A:G', function ($row) {
                        $row->setAlignment('center');
                        $row->setFontFamily('Work Sans');
                        $row->setFontSize(10);
                    });
                    $sheet->setAutoSize(true);
                });
            })->export('xlsx');

        } catch (Exception $e) {
            print_r($e);
        }
    }

    public function jobOrderExport(Request $request) {
        // dd($request->all());
        try {
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            if (!empty($request->export_date)) {
                $date_range = explode(' to ', $request->export_date);
                $report_start_date = date('Y-m-d', strtotime(trim($date_range[0])));
                $report_end_date = date('Y-m-d', strtotime(trim($date_range[1])));
            } else {
                $report_start_date = date('Y-m-d', strtotime(date('01-m-Y')));
                $report_end_date = date('Y-m-d', strtotime(date('t-m-Y')));
            }

            $job_orders = JobOrder::select([
                'job_orders.*',
                'vehicles.registration_number as reg_no',
                'vehicles.chassis_number',
                'vehicles.engine_number',
            ])->with([
                'outlet',
                'outlet.state',
                'outlet.region',
                'customer',
                'serviceAdviser',
                'status',
            ])
            ->join('vehicles','vehicles.id','job_orders.vehicle_id')
            ->whereRaw('DATE(job_orders.created_at) between "' . $report_start_date . '" and "' . $report_end_date . '"')
            ->whereNotNull('job_orders.status_id');
            
            if (!Entrust::can('gigo-report-all-outlet')) {
                if (Entrust::can('gigo-report-mapped-outlet')) {
                    $outlet_ids = Auth::user()->employee->outlets->pluck('id')->toArray();
                    array_push($outlet_ids, Auth::user()->employee->outlet_id);
                    $job_orders->whereIn('job_orders.outlet_id', $outlet_ids);
                }else {
                    $job_orders->where('job_orders.outlet_id', Auth::user()->working_outlet_id);
                }
            }
            $job_orders = $job_orders->get();

            $job_order_details = array();            
            if($job_orders){
                foreach($job_orders as $key => $job_order){
                    $gate_log = GateLog::where('job_order_id', $job_order->id)->first();
                    $job_card = JobCard::select([
                        'job_cards.id',
                        'job_cards.job_card_number',
                        'bays.name as bay',
                        'floor_supervisor.ecode as floor_supervisor_code',
                        'floor_supervisor.name as floor_supervisor_name',
                        'configs.name as status',
                    ])
                        ->leftjoin('bays', 'bays.id', 'job_cards.bay_id')
                        ->leftjoin('configs', 'configs.id', 'job_cards.status_id')
                        ->leftjoin('users as floor_supervisor', 'floor_supervisor.id', 'job_cards.floor_supervisor_id')
                        ->where('job_cards.job_order_id', $job_order->id)
                        ->first();

                    if(empty($job_order->gateLog->gate_out_time)){
                        if(!empty($job_card)){
                            $status = isset($job_card->status) ? $job_card->status : $job_order->status->name;
                        }else{
                           $status = isset($job_order->status->name) ? $job_order->status->name : '';
                        }
                    }else{
                        $status = isset($job_order->status->name) ? $job_order->status->name : '';
                    }

                    $gate_pass = GatePass::select('configs.name as status') ->leftjoin('configs', 'configs.id', 'gate_passes.status_id')->where('gate_passes.job_order_id', $job_order->id)->first();
                    $gate_pass_status = isset($gate_pass->status) ? $gate_pass->status : '';
                    
                    $job_order_details[] = [
                        isset($job_order->outlet->state->code) ? $job_order->outlet->state->code : '',                        
                        isset($job_order->outlet->region->code) ? $job_order->outlet->region->code : '',                        
                        $job_order->outlet->code,                        
                        $job_order->outlet->ax_name ? $job_order->outlet->ax_name : $job_order->outlet->name,                        
                        isset($job_order->customer->code) ? $job_order->customer->code : '',                        
                        isset($job_order->customer->name) ? $job_order->customer->name : '',                        
                        isset($job_order->customer->mobile_no) ? $job_order->customer->mobile_no : '',                        
                        $job_order->contact_number,
                        $job_order->number,
                        isset($gate_log) ? $gate_log->gate_in_date : '',
                        isset($gate_log) ? $gate_log->gate_out_date : '',
                        $job_order->reg_no,  
                        $job_order->chassis_number,  
                        $job_order->engine_number,  
                        isset($job_card->job_card_number) ? $job_card->job_card_number :'',  
                        isset($job_card->bay) ? $job_card->bay :'',  
                        isset($job_order->serviceAdviser->ecode) ? $job_order->serviceAdviser->ecode :'' ,  
                        isset($job_order->serviceAdviser->name) ? $job_order->serviceAdviser->name :'' ,
                        isset($job_card->floor_supervisor_code) ? $job_card->floor_supervisor_code :'',  
                        isset($job_card->floor_supervisor_name) ? $job_card->floor_supervisor_name :'',  
                        $status,
                        $gate_pass_status,
                    ];    
                }
            }

            ob_end_clean();
            ob_start();

            $header = [
                'State',
                'Region',
                'Outlet Code',
                'Outlet Name',
                'Customer Code',
                'Customer Name',
                'Mobile Number',
                'Service Contact Number',
                'Job Order Number',
                'GateIn Date & Time',
                'GateOut Date & Time',
                'Registration Number',
                'Chassis Number',
                'Engine Number',
                'Job Card Number',
                'Bay',
                'Service Advisor Code',
                'Service Advisor Name',
                'Floor Supervisor Code',
                'Floor Supervisor Name',
                'Status',
                'Gate Pass Status',
            ];

            $time_stamp = date('Y_m_d_h_i_s');
            Excel::create('Job Order Report - ' . $time_stamp, function ($excel) use ($header, $job_order_details) {
                $excel->sheet('Job Orders', function ($sheet) use ($header, $job_order_details) {
                    $sheet->freezeFirstRow();
                    $sheet->fromArray($job_order_details, null, 'A1');
                    $sheet->row(1, $header);
                    $sheet->row(1, function ($row) {
                        $row->setBackground('#bbc0c9');
                        $row->setAlignment('center');
                        $row->setFontSize(10);
                        $row->setFontFamily('Work Sans');
                        $row->setFontWeight('bold');
                    });
                    $sheet->cell('A:T', function ($row) {
                        $row->setAlignment('center');
                        $row->setFontFamily('Work Sans');
                        $row->setFontSize(10);
                    });
                    $sheet->setAutoSize(true);
                });
            })->export('xlsx');
        } catch (Exception $e) {
            print_r($e);
        }
    }
}