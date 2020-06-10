<?php

namespace Abs\GigoPkg;
use App\Http\Controllers\Controller;
use Abs\GigoPkg\Complaint;
use Auth;
use Carbon\Carbon;
use DB;
use Entrust;
use Illuminate\Http\Request;
use Validator;
use Yajra\Datatables\Datatables;

class ComplaintController extends Controller {

	public function __construct() {
		$this->data['theme'] = config('custom.theme');
	}

	public function getComplaintFilterData() {
		$this->data['extras'] = [
			'status' => [
				['id' => '', 'name' => 'Select Status'],
				['id' => '1', 'name' => 'Active'],
				['id' => '0', 'name' => 'Inactive'],
			],
		];
		$this->data['complaint_group'] = collect(ComplaintGroup::select('id','code')->where('company_id',Auth::user()->company_id)->get())->prepend(['id' => '', 'code' => 'Select Complaint Group']);
		return response()->json($this->data);
	}

	public function getComplaintList(Request $request) {
		$complaints = Complaint::withTrashed()

			->select([
				'complaints.id',
				'complaints.name',
				'complaints.code',
				'complaint_groups.code',
				'complaints.hours',
				'complaints.kms',
				'complaints.months',
				DB::raw('IF(complaints.deleted_at IS NULL, "Active","Inactive") as status'),
			])
			->leftJoin('complaint_groups', 'complaint_groups.id', 'complaints.group_id')
			->where('complaints.company_id', Auth::user()->company_id)
			->where(function ($query) use ($request) {
				if (!empty($request->short_name)) {
					$query->where('complaints.code', 'LIKE', '%' . $request->short_name . '%');
				}
			})
			->where(function ($query) use ($request) {
				if (!empty($request->name)) {
					$query->where('complaints.name', 'LIKE', '%' . $request->name . '%');
				}
			})
			->where(function ($query) use ($request) {
				if ($request->status == '1') {
					$query->whereNull('complaints.deleted_at');
				} else if ($request->status == '0') {
					$query->whereNotNull('complaints.deleted_at');
				}
			})
		;

		return Datatables::of($complaints)
			 ->addColumn('status', function ($complaints) {
				$status = $complaints->status == 'Active' ? 'green' : 'red';
				return '<span class="status-indigator ' . $status . '"></span>' . $complaints->status;
			})
			->addColumn('action', function ($complaints) {
				$img1 = asset('public/themes/' . $this->data['theme'] . '/img/content/table/edit-yellow.svg');
				$img1_active = asset('public/themes/' . $this->data['theme'] . '/img/content/table/edit-yellow-active.svg');
				$img_delete = asset('public/themes/' . $this->data['theme'] . '/img/content/table/delete-default.svg');
				$img_delete_active = asset('public/themes/' . $this->data['theme'] . '/img/content/table/delete-active.svg');
				$output = '';
				if (Entrust::can('edit-complaint')) {
					$output .= '<a href="#!/gigo-pkg/complaint/edit/' . $complaints->id . '" id = "" title="Edit"><img src="' . $img1 . '" alt="Edit" class="img-responsive" onmouseover=this.src="' . $img1 . '" onmouseout=this.src="' . $img1 . '"></a>';
				}
				if (Entrust::can('delete-complaint')) {
					$output .= '<a href="javascript:;" data-toggle="modal" data-target="#complaint-delete-modal" onclick="angular.element(this).scope().deleteComplaint('.$complaints->id.')" title="Delete"><img src="' . $img_delete . '" alt="Delete" class="img-responsive delete" onmouseover=this.src="' . $img_delete . '" onmouseout=this.src="' . $img_delete . '"></a>';
				}
				return $output;
			})
			->make(true);
	}

	public function getComplaintFormData(Request $request) {
		$id = $request->id;
		if (!$id) {
			$complaint = new Complaint;
			$action = 'Add';
		} else {
			$complaint = Complaint::withTrashed()->find($id);
			$action = 'Edit';
		}
		$this->data['complaint_group'] = collect(ComplaintGroup::select('id','code')->where('company_id',Auth::user()->company_id)->get())->prepend(['id' => '', 'code' => 'Select Complaint Group']);
		$this->data['success'] = true;
		$this->data['complaint'] = $complaint;
		$this->data['action'] = $action;
		return response()->json($this->data);
	}

	public function saveComplaint(Request $request) {
		//dd($request->all());
		try {
			$error_messages = [
				'code.required' => 'Code is Required',
				'code.unique' => 'Code is already taken',
				'code.min' => 'Code is Minimum 3 Charachers',
				'code.max' => 'Code is Maximum 32 Charachers',
				'name.unique' => 'Name is already taken',
				'name.min' => 'Name is Minimum 3 Charachers',
				'name.max' => 'Name is Maximum 191 Charachers',
				'group_id.required' => 'Group is Required',
				'kms.max' => 'Kilometer is Maximum 10 Charachers',
				'hours.max' => 'Hours is Maximum 10 Charachers',
				'months.max' => 'Hours is Maximum 8 Charachers',
			];
			$validator = Validator::make($request->all(), [
				'code' => [
					'required:true',
					'min:3',
					'max:32',
					'unique:complaint,code,' . $request->id . ',id,company_id,' . Auth::user()->company_id,
				],
				'name' => [
					'nullable',
					'min:3',
					'max:191',
					'unique:complaint,name,' . $request->id . ',id,company_id,' . Auth::user()->company_id,
				],
				'group_id' => [
					'nullable',
					'min:3',
					'max:191',
					'unique:complaint,group_id,' . $request->id . ',id,company_id,' . Auth::user()->company_id,
				],
			], $error_messages);
			if ($validator->fails()) {
				return response()->json(['success' => false, 'errors' => $validator->errors()->all()]);
			}

			DB::beginTransaction();
			if (!$request->id) {
				$complaint_group = new ComplaintGroup;
				$complaint_group->created_by_id = Auth::user()->id;
				$complaint_group->created_at = Carbon::now();
				$complaint_group->updated_at = NULL;
			} else {
				$complaint_group = ComplaintGroup::withTrashed()->find($request->id);
				$complaint_group->updated_by_id = Auth::user()->id;
				$complaint_group->updated_at = Carbon::now();
			}
			$complaint_group->company_id = Auth::user()->company_id;
			$complaint_group->fill($request->all());
			if ($request->status == 'Inactive') {
				$complaint_group->deleted_at = Carbon::now();
			} else {
				$complaint_group->deleted_at = NULL;
			}
			$complaint_group->save();

			DB::commit();
			if (!($request->id)) {
				return response()->json([
					'success' => true,
					'message' => 'Complaint Group Added Successfully',
				]);
			} else {
				return response()->json([
					'success' => true,
					'message' => 'Complaint Group Updated Successfully',
				]);
			}
		} catch (Exceprion $e) {
			DB::rollBack();
			return response()->json([
				'success' => false,
				'error' => $e->getMessage(),
			]);
		}
	}

	public function deleteComplaint(Request $request) {
		DB::beginTransaction();
		//dd($request->id);
		try {
			$complaint_group = ComplaintGroup::withTrashed()->where('id', $request->id)->forceDelete();
			if ($complaint_group) {
				DB::commit();
				return response()->json(['success' => true, 'message' => 'Complaint Group Deleted Successfully']);
			}
		} catch (Exception $e) {
			DB::rollBack();
			return response()->json(['success' => false, 'errors' => ['Exception Error' => $e->getMessage()]]);
		}
	}

	
}