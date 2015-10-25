/**
 * Master Controller
 */

angular.module('main')
    .controller('MainCtrl', ['$scope', '$http', function ($scope, $http) {
        $scope.propertyLists = [];
        var url = "";
        var revisionId;
        

        $scope.select = function (propertyList) {
            $scope.propertyLists.forEach(function (item) {
                if (propertyList !== item) {
                    item.isSelected = false
                }
            });
            propertyList.isSelected = !propertyList.isSelected;
            if (propertyList.isSelected) {
                viewer.selectObjects(propertyList.oid);
            }

        };

        $scope.saveProperty = function(property){
        	console.log("api call");
        	$http.post(url, {id:property.oid, Key : property.name, Value:property.value, objects : viewer.getSelectedObjects()})
        };

        var viewer = function () {
            var loadedModel, clickSelect, firstId, selectedObjectIds = [];
            var preLoadQuery = {
                queries: [
                    {
                        type: "IfcProduct",
                        includeAllSubtypes: true
                    },
                    {
                        type: "IfcPropertySet",
                        include: [
                            {field: "HasProperties"},
                            {
                                field: "PropertyDefinitionOf"
                            }
                        ]
                    },
                    {
                        type: "IfcRelDefinesByProperties",
                        include: {field: "RelatingPropertyDefinition"}
                    }
                ]
            };

            function Notifier() {
                this.setSuccess = function (status) {
                    console.log("success", status);
                };
                this.setInfo = function (info) {
                    console.log("info", info);
                };
                this.setError = function (error) {
                    console.log("error", error);
                };
            }


            function nodeSelected(revId, node) {
                if (!firstId) {
                    firstId = node.id;

                    selectedObjectIds[node.id] = {selected: true};
                    $scope.$apply(function () {
                        $scope.propertyLists = []
                    });
                    var object = loadedModel.objects[node.id];
                    if (object) {
                        var ifcRels = object.object._rIsDefinedBy;
                        if (ifcRels) {
                            ifcRels.forEach(function (relId) {
                                var ifcRel = loadedModel.objects[relId];
                                if (ifcRel && ifcRel.object._t === 'IfcRelDefinesByProperties' && loadedModel.objects[ifcRel.object._rRelatingPropertyDefinition].object._t
                                    === 'IfcPropertySet') {
                                    var propSetId = ifcRel.object._rRelatingPropertyDefinition;
                                    var propertySet = loadedModel.objects[propSetId];
                                    var propSetObject = {name: propertySet.object.Name, oid: propSetId, isSelected: false, properties: []};
                                    if (propertySet.object._rHasProperties) {
                                        propertySet.object._rHasProperties.forEach(function (matId) {
                                            var material = loadedModel.objects[matId];
                                            propSetObject.properties.push({name: material.object.Name, value: material.object._eNominalValue._v, oid: matId})
                                        });
                                    }
                                    $scope.$apply(function () {
                                        $scope.propertyLists.push(propSetObject)
                                    })

                                }
                            });
                        }
                    }
                }
            }

            function nodeUnselected(revId, node) {
            	if(!!selectedObjectIds[node.id]){
            		selectedObjectIds[node.id] = undefined;
            	}
            }

            return {
                init: function init(address, projectId) {
                    var notifier = new Notifier();

                    loadBimServerApi(address, notifier, new Date().getTime(), function (api, serverInfo) {
                        var bimServerApi = api;
                        bimServerApi.init(function () {
                            bimServerApi.login("admin@bimserver.com", "admin", function (data) {
                                var viewer = new BIMSURFER.Viewer(bimServerApi, "viewport");
                                viewer.loadScene(function () {
                                    clickSelect = viewer.getControl("BIMSURFER.Control.ClickSelect");
                                    clickSelect.activate();
                                    clickSelect.events.register('select', nodeSelected);
                                    clickSelect.events.register('unselect', nodeUnselected);
                                }, {useCapture: true});

                                var oidsNotLoaded = [];
                                var models = {};
                                bimServerApi.call("Bimsie1ServiceInterface", "getProjectByPoid", {poid: projectId}, function(data){
                                	revisionId = data.lastRevisionId
                                	console.log(revisionId)
							    }).done(function(){
							    	bimServerApi.getModel(projectId, revisionId, "ifc2x3tc1", false, function (model) {
	                                    loadedModel = model;
	                                    model.loaded = true;
	                                    models[revisionId] = model;
	                                    model.query(preLoadQuery, function (loadedObject) {

	                                        if (loadedObject.isA("IfcProduct")) {
	                                            oidsNotLoaded.push(loadedObject.oid);
	                                            loadedObject.trans.mode = 0;
	                                        }
	                                    }).done(function () {
	                                        var geoLoad = new GeometryLoader(bimServerApi, models, viewer);
	                                        geoLoad.setLoadOids([revisionId], oidsNotLoaded);
	                                        viewer.loadGeometry(geoLoad);
	                                    });
	                                });
							    })
                                
                                

                            });
                        });
                    });

                },
                selectObjects: function selectObjects(selectedId) {
                    var selectedRel = loadedModel.objects[selectedId];
                    var relatedObjects = selectedRel.object._rRelatedObjects;
                    if (relatedObjects) {
                        relatedObjects.forEach(function (oid) {
                            if (!selectedObjectIds[oid]) {
                                clickSelect.pick({nodeId: oid});
                                console.log(loadedModel.objects[oid]);
                            }
                        });
                    }
                },getSelectedObjects: function getGuids() {
                	var guids = [];
                    guids.push({oid: firstId, guid: loadedModel.objects[firstId].getGlobalId()})
//                    selectedObjectIds.forEach(function (oid) {
//                        if (!selectedObjectIds[oid]) {
//                            guids.push({oid: oid, guid: loadedModel[oid].getGlobalId()})
//                        }
//                    });
                    return guids
                }

            }
        }();
        var address = 'http://10.30.22.250:8082';
        var projectId = 196609;
        
        viewer.init(address, projectId);
    }]);

