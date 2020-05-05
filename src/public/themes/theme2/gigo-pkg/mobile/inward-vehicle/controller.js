app.component('mobileInwardVehicleList', {
    templateUrl: mobile_inward_vehicle_list_template_url,
    controller: function($http, $location, HelperService, $scope, $routeParams, $rootScope, $cookies) {
        $scope.loading = true;
        var self = this;
        if (!HelperService.isLoggedIn()) {
            $location.path('/gigo-pkg/mobile/login');
            return;
        }
        $scope.hasPerm = HelperService.hasPerm;
        $scope.user = JSON.parse(localStorage.getItem('user'));
        $rootScope.loading = false;
    }
});
//-------------------------------------------------------------------------------------------------------------------
//-------------------------------------------------------------------------------------------------------------------
app.component('mobileInwardVehicleDetailView', {
    templateUrl: mobile_inward_vehicle_detail_view_template_url,
    controller: function($http, $location, HelperService, $scope, $routeParams, $rootScope, $cookies) {
        $scope.loading = true;
        var self = this;
        if (!HelperService.isLoggedIn()) {
            $location.path('/gigo-pkg/mobile/login');
            return;
        }
        $scope.hasPerm = HelperService.hasPerm;
        $scope.user = JSON.parse(localStorage.getItem('user'));
        $rootScope.loading = false;
    }
});