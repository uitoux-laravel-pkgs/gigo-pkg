<?php
Route::group(['namespace' => 'App\Http\Controllers\Api', 'middleware' => ['auth:api']], function () {
	Route::group(['prefix' => 'api'], function () {

		Route::group(['prefix' => 'job-order'], function () {
			$controller = 'JobOrderController';
			Route::get('index', $controller . '@index');
			Route::get('read/{id}', $controller . '@read');
		});

		Route::get('job-card/index', JobCardController::class . '@index');

		Route::get('service-type/options', ServiceTypeController::class . '@options');

		Route::group(['prefix' => 'wjor-repair-order'], function () {
			$controller = 'WjorRepairOrder';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('save-from-form-data', $controller . 'Controller@saveFromFormData');
			Route::post('save-from-ng-data', $controller . 'Controller@saveFromNgData');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'wjor-part'], function () {
			$controller = 'WjorPart';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('save-from-form-data', $controller . 'Controller@saveFromFormData');
			Route::post('save-from-ng-data', $controller . 'Controller@saveFromNgData');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'repair-order'], function () {
			$controller = 'RepairOrder';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'part'], function () {
			$controller = 'Part';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'job-order'], function () {
			$controller = 'JobOrder';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('save-it', $controller . 'Controller@saveIt');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'warranty-job-order-request'], function () {
			$controller = 'WarrantyJobOrderRequest';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('save-it', $controller . 'Controller@saveIt');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
			Route::post('send-to-approval', $controller . 'Controller@sendToApproval');
			Route::post('approve', $controller . 'Controller@approve');
			Route::post('reject', $controller . 'Controller@reject');
		});

		Route::group(['prefix' => 'vehicle-primary-application'], function () {
			$controller = 'VehiclePrimaryApplication';
			Route::get('index', $controller . 'Controller@index');
			Route::get('read/{id}', $controller . 'Controller@read');
			Route::post('save', $controller . 'Controller@save');
			Route::post('remove', $controller . 'Controller@remove');
			Route::get('options', $controller . 'Controller@options');
		});

		Route::group(['prefix' => 'vehicle-secondary-application'], function () {
			$controller = 'VehicleSecondaryApplicationController';
			Route::get('index', $controller . '@index');
			Route::get('read/{id}', $controller . '@read');
			Route::post('save', $controller . '@save');
			Route::post('remove', $controller . '@remove');
			Route::get('options', $controller . '@options');
		});

		Route::group(['prefix' => 'part-supplier'], function () {
			$controller = 'PartSupplierController';
			Route::get('index', $controller . '@index');
			Route::get('read/{id}', $controller . '@read');
			Route::post('save', $controller . '@save');
			Route::post('remove', $controller . '@remove');
			Route::get('options', $controller . '@options');
		});

		Route::group(['prefix' => 'complaint'], function () {
			$controller = 'ComplaintController';
			Route::get('index', $controller . '@index');
			Route::get('read/{id}', $controller . '@read');
			Route::post('save', $controller . '@save');
			Route::post('remove', $controller . '@remove');
			Route::get('options', $controller . '@options');
		});

		Route::group(['prefix' => 'fault'], function () {
			$controller = 'FaultController';
			Route::get('index', $controller . '@index');
			Route::get('read/{id}', $controller . '@read');
			Route::post('save', $controller . '@save');
			Route::post('remove', $controller . '@remove');
			Route::get('options', $controller . '@options');
		});

		//SAVE GATE IN ENTRY
		Route::get('gate-in-entry/get-form-data', 'GateInController@getFormData');
		Route::post('gate-in-entry/create', 'GateInController@createGateInEntry');

		//VEHICLE INWARD
		Route::post('vehicle-inward/get', 'VehicleInwardController@getGateInList');

		//VEHICLE INWARD VIEW DATA
		Route::post('vehicle-inward/get-view-data', 'VehicleInwardController@getVehicleInwardView');

		//CUSTOMER DETAIL FORM DATA AND SAVE
		Route::post('vehicle-inward/view', 'VehicleInwardController@getVehicleInwardViewData');
		Route::post('vehicle-inward/get-vehicle-detail', 'VehicleInwardController@getVehicleDetail');

		//CUSTOMER DETAIL FORM DATA AND SAVE
		Route::post('vehicle-inward/get-customer-detail', 'VehicleInwardController@getCustomerDetail');
		Route::post('vehicle-inward/save-customer-detail', 'VehicleInwardController@saveCustomerDetail');

		//ORDER DETAIL FORM DATA AND SAVE
		Route::post('vehicle-inward/order-detail/get-form-data', 'VehicleInwardController@getOrderFormData');
		Route::post('vehicle-inward/order-detail/save', 'VehicleInwardController@saveOrderDetail');

		//VEHICLE GET INVENTORY FORM DATA AND SAVE
		Route::post('vehicle-inward/inventory/get-form-data', 'VehicleInwardController@getInventoryFormData');
		Route::post('vehicle-inward/inventory/save', 'VehicleInwardController@saveInventoryItem');

		//VOC GET FORM DATA AND SAVE
		Route::post('vehicle-inward/voc/get-form-data', 'VehicleInwardController@getVocFormData');
		Route::post('vehicle-inward/voc/save', 'VehicleInwardController@saveVoc');

		//ROAD TEST OBSERVATION GET FORM DATA AND SAVE
		Route::post('vehicle-inward/road-test-observation/get-form-data', 'VehicleInwardController@getRoadTestObservationFormData');
		Route::post('vehicle-inward/road-test-observation/save', 'VehicleInwardController@saveRoadTestObservation');

		//EXPERT DIAGNOSIS REPORT GET FORM DATA AND SAVE
		Route::post('vehicle-inward/expert-diagnosis-report/get-form-data', 'VehicleInwardController@getExpertDiagnosisReportFormData');
		Route::post('vehicle-inward/expert-diagnosis-report/save', 'VehicleInwardController@saveExpertDiagnosisReport');

		//VEHICLE INSPECTION GET FORM DATA AND SAVE
		Route::post('vehicle-inward/vehicle-inspection/get-form-data', 'VehicleInwardController@getVehicleInspectiongetFormData');
		Route::post('vehicle-inward/vehicle-inspection/save', 'VehicleInwardController@saveVehicleInspection');

		//DMS CHECKLIST SAVE
		Route::post('vehicle-inward/dms-checklist/save', 'VehicleInwardController@saveDmsCheckList');
		Route::post('vehicle-inward/dms-checklist/get-form-data', 'VehicleInwardController@getDmsCheckListFormData');

		//SCHEDULE MANINTENCE GET FORM DATA AND SAVE
		Route::post('vehicle-inward/schedule-maintenance/get-form-data', 'VehicleInwardController@getScheduleMaintenanceFormData');
		Route::post('vehicle-inward/schedule-maintenance/save', 'VehicleInwardController@saveScheduleMaintenance');

		//ADDTIONAL ROT AND PART GET FORM DATA AND SAVE
		Route::post('vehicle-inward/addtional-rot-part/get-form-data', 'VehicleInwardController@addtionalRotPartGetList');
		//ROT
		Route::post('vehicle-inward/repair-order-type-list/get', 'VehicleInwardController@getRepairOrderTypeList');
		Route::post('vehicle-inward/get-repair-order-list/get', 'VehicleInwardController@getAddtionalRotList');
		Route::post('vehicle-inward/repair-order/get-form-data', 'VehicleInwardController@getRepairOrderData');

		Route::post('vehicle-inward/job-order-repair-order/get-form-data', 'VehicleInwardController@getJobOrderRepairOrderData');
		Route::post('vehicle-inward/add-repair-order/save', 'VehicleInwardController@saveAddtionalLabour');
		//PART
		Route::post('vehicle-inward/part-list/get', 'VehicleInwardController@getPartList');
		Route::post('vehicle-inward/part/get-form-data', 'VehicleInwardController@getPartData');

		Route::post('vehicle-inward/job_order-part/get-form-data', 'VehicleInwardController@getJobOrderPartData');
		Route::post('vehicle-inward/add-part/save', 'VehicleInwardController@saveAddtionalPart');

		Route::post('vehicle-inward/addtional-rot-part/save', 'VehicleInwardController@saveAddtionalRotPart');

		//ESTIMATE GET FORM DATA AND SAVE
		Route::post('vehicle-inward/estimate/get-form-data', 'VehicleInwardController@getEstimateFormData');
		Route::post('vehicle-inward/estimate/save', 'VehicleInwardController@saveEstimate');

		//ESTIMATION DENIED GET FORM DATA AND SAVE
		Route::post('vehicle-inward/estimation-denied/get-form-data', 'VehicleInwardController@getEstimationDeniedFormData');
		Route::post('vehicle-inward/estimation-denied/save', 'VehicleInwardController@saveEstimateDenied');

		//CUSTOMER CONFIRMATION GET FORM DATA SAVE
		Route::post('vehicle-inward/customer-confirmation/get-form-data', 'VehicleInwardController@getCustomerConfirmationFormData');
		Route::post('vehicle-inward/customer-confirmation/save', 'VehicleInwardController@saveCustomerConfirmation');

		//INITIATE JOB SAVE
		Route::post('vehicle-inward/initiate-job/save', 'VehicleInwardController@saveInitiateJob');

		//GTE STATE BASED COUNTRY
		Route::get('get-state/country-id/{country_id}', 'VehicleInwardController@getState');

		//GET CITY BASED STATE
		Route::get('get-city/state-id/{state_id}', 'VehicleInwardController@getcity');

		//Update Job Card
		Route::post('vehicle-inward/get-update-jc-form-data', 'JobCardController@getUpdateJcFormData');
		Route::post('vehicle-inward/job-card/save', 'JobCardController@saveJobCard');
		Route::post('vehicle-inward/send/customer/otp', 'JobCardController@sendCustomerOtp');
		Route::post('vehicle-inward/verify/otp', 'JobCardController@verifyOtp');
		Route::post('vehicle-inward/estimate/link/generate', 'JobCardController@generateUrl');

		//GET BAY ASSIGNMENT FORM DATA
		Route::post('job-card/bay/get-form-data', 'JobCardController@getBayFormData');
		//SAVE BAY ASSIGNMENT
		Route::post('job-card/bay/save', 'JobCardController@saveBay');

		//GET BAY DATA
		Route::post('job-card/bay-view/get', 'JobCardController@getBayViewData');

		//MY JOB CARD
		Route::post('get-my-job-card-list', 'MyJobCardController@getMyJobCardList');

		//Jobcard View Labour Assignment
		Route::post('job-card/labour-assignment/get-form-data', 'JobCardController@LabourAssignmentFormData');
		Route::post('job-card/get-mechanic', 'JobCardController@getMechanic');
		Route::post('job-card/save-mechanic', 'JobCardController@saveMechanic');
		Route::post('job-card/mechanic-time-log', 'JobCardController@getMechanicTimeLog');

		//JobOrder Repair order form save
		Route::post('labour-assignment-form-save', 'JobCardController@LabourAssignmentFormSave');

		//Material-GatePass Vendor list
		Route::post('get-vendor-list', 'JobCardController@VendorList');

		//Material-GatePass Vendor Details
		Route::get('get-vendor-details/{vendor_id}', 'JobCardController@VendorDetails');

		// JOB CARD LIST
		Route::post('job-card/get', 'JobCardController@getJobCardList');

		// JOB CARD TIME LOG
		Route::post('get-job-card-time-log', 'JobCardController@getJobCardTimeLog');

		//JOB CARD WORK COMPLETED
		Route::post('job-card/update-status', 'JobCardController@updateJobCardStatus');

		// JOB CARD MATRIAL GATE PASS VIEW
		Route::post('view-material-gate-pass', 'JobCardController@viewMeterialGatePass');

		//Job Card get OutwardDetail

		Route::post('view-material-gate-pass-detail', 'JobCardController@getMeterialGatePassOutwardDetail');

		// MY JOB CARD DATA
		Route::post('my-job-card-view', 'JobCardController@getMyJobCardData');

		//VIEW JOB CARD
		Route::get('view-job-card/{id}', 'JobCardController@viewJobCard');

		Route::post('save-my-job-card', 'JobCardController@saveMyJobCard');

		//JOB CARD LABOUR REVIEW
		Route::post('get-labour-review', 'JobCardController@getLabourReviewData');

		//JOB CARD RETURNABLE ITEM SAVE
		Route::post('labour-review-save', 'JobCardController@LabourReviewSave');

		//JOB CARD RETURNABLE ITEM SAVE
		Route::post('job-card/returnable-items/get', 'JobCardController@getReturnableItems');
		Route::post('job-card/returnable-items/get-form-data', 'JobCardController@getReturnableItemFormdata');

		Route::post('job-card/returnable-item/save', 'JobCardController@ReturnableItemSave');

		//Job Card View
		Route::post('jobcard/road-test-observation/get', 'JobCardController@getRoadTestObservation');
		Route::post('jobcard/expert-diagnosis/get', 'JobCardController@getExpertDiagnosis');
		Route::post('jobcard/dms-checklist/get', 'JobCardController@getDmsCheckList');
		Route::post('jobcard/vehicle-inspection/get', 'JobCardController@getVehicleInspection');
		Route::post('jobcard/part-indent/get', 'JobCardController@getPartsIndent');
		Route::post('jobcard/schedule-maintenance/get', 'JobCardController@getScheduleMaintenance');
		Route::post('jobcard/payable-labour-part/get', 'JobCardController@getPayableLabourPart');
		Route::post('jobcard/estimate/get', 'JobCardController@getEstimate');
		Route::post('jobcard/estimate-status/get', 'JobCardController@getEstimateStatus');
		Route::post('jobcard/outward-item/delete', 'JobCardController@deleteOutwardItem');
		Route::post('jobcard/gate-in-detial/get', 'JobCardController@getGateInDetail');
		Route::post('jobcard/vehicle-detial/get', 'JobCardController@getVehicleDetail');
		Route::post('jobcard/customer-detial/get', 'JobCardController@getCustomerDetail');
		Route::post('jobcard/order-detial/get', 'JobCardController@getOrderDetail');
		Route::post('jobcard/inventory/get', 'JobCardController@getInventory');
		Route::post('jobcard/capture-voc/get', 'JobCardController@getCaptureVoc');

		//Material-GatePass Details Save
		Route::post('save-material-gate-pass-detail', 'JobCardController@saveMaterialGatePassDetail');

		//Material-GatePass Items Save
		Route::post('save-material-gate-pass-item', 'JobCardController@saveMaterialGatePassItem');

		//Material-GatePass Details List
		Route::post('get-material-gate-pass-list', 'MaterialGatePassController@getMaterialGatePass');

		//Material-GatePass Detail
		Route::get('get-material-gate-pass-detail/{id}', 'MaterialGatePassController@getMaterialGatePassViewData');

		//Material-GatePass Gate in and out
		Route::post('save-gate-in-out-material-gate-pass', 'MaterialGatePassController@materialGateInAndOut');
		//Save Material Gate Out Confirm
		Route::post('save-gate-out-confirm-material-gate-pass', 'MaterialGatePassController@materialGateOutConfirm');
		//Resend OTP for Material Gate Pass
		Route::get('material-gate-out-otp-resend/{id}', 'MaterialGatePassController@materialCustomerOtp');

		//VIEW BILL DETAILS
		Route::post('job-card/bill-detail/view', 'JobCardController@viewBillDetails');
		Route::post('job-card/bill-update/get-form-data', 'JobCardController@getBillDetailFormData');
		Route::post('job-card/bill-update/', 'JobCardController@updateBillDetails');

		//SPLIT ORDER DETAILS
		Route::post('job-card/split-order/view', 'JobCardController@viewSplitOrderDetails');

		//VEHICLE GATE PASS LIST
		Route::post('get-vehicle-gate-pass-list', 'VehicleGatePassController@getVehicleGatePassList');
		Route::get('view-vehicle-gate-pass/{gate_log_id}', 'VehicleGatePassController@viewVehicleGatePass');
		Route::post('gate-out-vehicle/save', 'VehicleGatePassController@saveVehicleGateOutEntry');

	});
});
