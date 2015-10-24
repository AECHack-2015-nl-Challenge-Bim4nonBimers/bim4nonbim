/**
 * Master Controller
 */

angular.module('main')
    .controller('MainCtrl', ['$scope',function($scope) {
    	$scope.propertyLists = [{id:'10',name:'test',properties:[{name:'1',value:'val1'}, {name:'2',value:'val2'}]}]
    	
    	
    	var viewer = function () {
    	    var bimServerApi, viewer;
    	    var preLoadQuery = {
    	        queries: [
    	            {
    	                type: "IfcProduct",
    	                includeAllSubtypes: true
    	            }
    	        ]
    	    };

    	    function Notifier() {
    	        this.setSelector = function (selector) {
    	        };
    	        this.clear = function () {
    	        };
    	        this.resetStatus = function () {
    	        };
    	        this.resetStatusQuick = function () {
    	        };
    	        this.setSuccess = function (status, timeToShow) {
    	            console.log("success", status);
    	        };
    	        this.setInfo = function (info, timeToShow) {
    	            console.log("info", info);
    	        };
    	        this.setError = function (error) {
    	            console.log("error", error);
    	        };
    	    }


    	    function nodeSelected() {
    	        //todo: when element is selected
    	    }

    	    function nodeUnselected() {
    	//todo: when element is unselected
    	    }

    	    return {
    	        init: function init() {
    	            var address = 'http://10.30.22.250:8082';
    	            var notifier = new Notifier();

    	            loadBimServerApi(address, notifier, new Date().getTime(), function (api, serverInfo) {
    	                bimServerApi = api;
    	                bimServerApi.init(function () {
    	                    bimServerApi.login("admin@bimserver.com ", "admin", function (data) {
    	                        console.log(data);
    	                        viewer = new BIMSURFER.Viewer(bimServerApi, "viewport");
    	                        viewer.loadScene(function () {
    	                            var clickSelect = viewer.getControl("BIMSURFER.Control.ClickSelect");
    	                            clickSelect.activate();
    	                            clickSelect.events.register('select', nodeSelected);
    	                            clickSelect.events.register('unselect', nodeUnselected);
    	                        }, {useCapture: true});

    	                        var oidsNotLoaded = [], model, ifcProject;
    	                        var models = {};
    	                        bimServerApi.getModel(131073, 65539, "ifc2x3tc1", false, function (model) {
    	                            model.loaded = true;
    	                            console.log("before query");
    	                            models[65539] = model;
    	                            model.query(preLoadQuery, function (loadedObject) {
    	                                console.log(loadedObject);
    	                                if (loadedObject.isA("IfcProduct")) {
    	                                    oidsNotLoaded.push(loadedObject.oid);
    	                                    loadedObject.trans.mode = 0;
    	                                }
    	                            }).done(function () {
    	                                var geoLoad = new GeometryLoader(bimServerApi, models, viewer);
    	                                geoLoad.setLoadOids([65539], oidsNotLoaded);
    	                                viewer.loadGeometry(geoLoad);
    	                            });
    	                        });

    	                    });
    	                });
    	            });

    	        }

    	    }
    	}();

    	//65539 revisionId
    	//viewer.init(65539);
    	
    	
	}])

