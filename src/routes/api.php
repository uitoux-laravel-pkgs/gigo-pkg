<?php
Route::group(['namespace' => 'Abs\GigoPkg\Api', 'middleware' => ['auth:api']], function () {
	Route::group(['prefix' => 'api/gigo-pkg'], function () {
		//Route::post('punch/status', 'PunchController@status');

		//SAVE GATE IN ENTRY
		Route::post('gate-log/save', 'VehicleGatePassController@saveVehicleGateInEntry');

		//VEHICLE INWARD
		Route::post('vehicle-inward/get-list', 'VehicleInwardController@getVehicleInwardList');
		Route::post('get-vehicle-inward-view', 'VehicleInwardController@getVehicleInwardViewData');

		//VEHICLE GATE PASS LIST
		Route::post('get-vehicle-gate-pass-list', 'VehicleGatePassController@getVehicleGatePassList');
		Route::get('view-vehicle-gate-pass/{gate_log_id}', 'VehicleGatePassController@viewVehicleGatePass');
		Route::post('gate-out-vehicle/save', 'VehicleGatePassController@saveVehicleGateOutEntry');

		//VEHICLE GET JOB ORDER FORM DATA AND SAVE
		Route::get('get-job-order-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getJobOrderFormData');
		Route::post('save-job-order', 'VehicleInwardController@saveJobOrder');

		//VEHICLE GET INVENTORY FORM DATA AND SAVE
		Route::get('get-inventory-form-data/gate-log/{id}', 'VehicleInwardController@getInventoryFormData');
		Route::post('save-inventory-item', 'VehicleInwardController@saveInventoryItem');

		//VEHICLE GET FORM DATA AND SAVE
		Route::get('get-vehicle-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getVehicleFormData');
		Route::post('save-vehicle', 'VehicleInwardController@saveVehicle');

		//VEHICLE GET FORM DATA AND SAVE
		Route::get('get-customer-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getCustomerFormData');
		Route::post('save-customer', 'VehicleInwardController@saveCustomer');

		//VEHICLE INSPECTION GET FORM DATA AND SAVE
		Route::get('get-vehicle-inspection-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getVehicleInspectiongeFormData');
		Route::post('save-vehicle-inspection', 'VehicleInwardController@saveVehicleInspection');

		//VOC GET FORM DATA AND SAVE
		Route::get('get-voc-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getVocFormData');
		Route::post('save-voc', 'VehicleInwardController@saveVoc');

		//ROAD TEST OBSERVATION GET FORM DATA AND SAVE
		Route::get('get-road-test-observation-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getRoadTestObservationFormData');
		Route::post('save-road-test-observation', 'VehicleInwardController@saveRoadTestObservation');

		//DMS CHECKLIST SAVE
		Route::post('save-dms-checklist', 'VehicleInwardController@saveDmsCheckList');

		//ADDTIONAL ROT AND PART GET FORM DATA AND SAVE
		Route::get('get-addtional-rot-part/{id}', 'VehicleInwardController@addtionalRotPartGetList');
		//ROT
		Route::get('get-repair-order-type-list/{id}', 'VehicleInwardController@getAddtionalRotFormData');
		Route::get('get-repair-order-list/repair-order-type-id/{repair_order_type_id}', 'VehicleInwardController@getAddtionalRotList');
		Route::get('get-repair-order-data/{id}', 'VehicleInwardController@getRepairOrderData');
		//PART
		Route::get('get-part-list/{id}', 'VehicleInwardController@getPartList');
		Route::get('get-part-data/{id}', 'VehicleInwardController@getPartData');

		Route::post('save-addtional-rot-part', 'VehicleInwardController@saveAddtionalRotPart');

		//SCHEDULE MANINTENCE GET FORM DATA AND SAVE
		Route::get('get-schedule-maintenance-form-data', 'VehicleInwardController@getScheduleMaintenanceFormData');
		Route::post('save-schedule-maintenance', 'VehicleInwardController@saveScheduleMaintenance');

		//EXPERT DIAGNOSIS REPORT GET FORM DATA AND SAVE
		Route::get('get-expert-diagnosis-report-form-data/gate-log/{gate_log_id}', 'VehicleInwardController@getExpertDiagnosisReportFormData');
		Route::post('save-expert-diagnosis-report', 'VehicleInwardController@saveExpertDiagnosisReport');

		//ESTIMATE GET FORM DATA AND SAVE
		//issue: Route naming
		Route::get('get-estimate-form-data/{id}', 'VehicleInwardController@getEstimateFormData');
		Route::post('save-estimate', 'VehicleInwardController@saveEstimate');

		//ESTIMATION DENIED GET FORM DATA AND SAVE
		//issue: Route naming
		Route::get('get-estimation-denied-form-data/{id}', 'VehicleInwardController@getEstimationDeniedFormData');
		Route::post('save-estimation-denied', 'VehicleInwardController@saveEstimateDenied');

		//CUSTOMER CONFIRMATION SAVE AND GET DATA
		Route::post('save-customer-confirmation', 'VehicleInwardController@saveCustomerConfirmation');

		//INITIATE JOB SAVE
		Route::post('save-initiate-job', 'VehicleInwardController@saveInitiateJob');

		//GTE STATE BASED COUNTRY
		Route::get('get-state/country-id/{country_id}', 'VehicleInwardController@getState');

		//GET CITY BASED STATE
		Route::get('get-city/state-id/{state_id}', 'VehicleInwardController@getcity');

		//Save Job Card
		//issue: Route naming
		Route::post('save-job-card', 'JobCardController@saveJobCard');

		//GET BAY ASSIGNMENT FORM DATA
		Route::get('get-bay-form-data/{job_card_id}', 'JobCardController@getBayFormData');

		//MY JOB CARD
		Route::post('get-my-job-card-list', 'MyJobCardController@getMyJobCardList');

		//SAVE BAY ASSIGNMENT
		Route::post('save-bay', 'JobCardController@saveBay');

		//Jobcard View Labour Assignment
		Route::get('get-labour-assignment-form-data/{jobcard_id}', 'JobCardController@LabourAssignmentFormData');

		//JobOrder Repair order form save
		Route::post('labour-assignment-form-save', 'JobCardController@LabourAssignmentFormSave');

		//Material-GatePass Vendor list
		Route::post('get-vendor-list', 'JobCardController@VendorList');

		//Material-GatePass Vendor Details
		Route::get('get-vendor-details/{vendor_id}', 'JobCardController@VendorDetails');

		// JOB CARD LIST
		Route::post('get-job-card-list', 'JobCardController@getJobCardList');

		// JOB CARD TIME LOG
		Route::get('get-job-card-time-log/{job_card_id}', 'JobCardController@getJobCardTimeLog');

		// JOB CARD MATRIAL GATE PASS VIEW
		Route::get('view-material-gate-pass/{job_card_id}', 'JobCardController@viewMetirialGatePass');

		// MY JOB CARD DATA
		Route::post('get-my-job-card-data', 'JobCardController@getMyJobCardData');

		//VIEW JOB CARD
		Route::get('view-job-card/{id}', 'JobCardController@viewJobCard');

		Route::post('save-my-job-card', 'JobCardController@saveMyJobCard');

		//JOB CARD LABOUR REVIEW
		Route::get('get-labour-review/{id}', 'JobCardController@getLabourReviewData');

		//JOB CARD RETURNABLE ITEM SAVE
		Route::post('labour-review-save', 'JobCardController@LabourReviewSave');

		//JOB CARD RETURNABLE ITEM SAVE
		Route::post('job-card-returnable-item-save', 'JobCardController@ReturnableItemSave');

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

	});
});
