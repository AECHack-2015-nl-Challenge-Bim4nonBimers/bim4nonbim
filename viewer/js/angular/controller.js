/**
 * Master Controller
 */

angular.module('main')
    .controller('MainCtrl', ['$scope', function ($scope) {
        $scope.propertyLists = []

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

        var viewer = function () {
            var bimServerApi, viewer, loadedModel, clickSelect, firstId, selectedobjects = [];
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

                    selectedobjects[node.id] = {selected: true};
                    $scope.$apply(function () {
                        $scope.propertyLists = []
                    });
                    var object = loadedModel.objects[node.id];
                    if (object) {
                        var propSets = object.object._rIsDefinedBy;
                        if (propSets) {
                            propSets.forEach(function (relId) {
                                var relDefByProp = loadedModel.objects[relId];
                                var materialId = relDefByProp.object._rRelatingPropertyDefinition; //materials
                                var mat = loadedModel.objects[materialId];
                                if (mat && "IfcPropertySet" === mat.getType()) {
                                    var object = {name: mat.getName(), oid: relId, isSelected: false, properties: []};
                                    if (mat.object._rHasProperties) {

                                        mat.object._rHasProperties.forEach(function (matId) {
                                            var material = loadedModel.objects[matId];
                                            if ("IfcPropertySingleValue" === material.getType()) {
                                                object.properties.push({name: material.getName(), value: material.object._eNominalValue._v})

                                            }
                                        });
                                    }
                                    $scope.$apply(function () {
                                        $scope.propertyLists.push(object)
                                    })
                                }


                            });
                        }
                    }
                }
            }

            function nodeUnselected(revId, node) {
                selectedobjects[node.id] = undefined;

            }

            return {
                init: function init() {
                    var address = 'http://10.30.22.250:8082';
                    var notifier = new Notifier();

                    loadBimServerApi(address, notifier, new Date().getTime(), function (api, serverInfo) {
                        bimServerApi = api;
                        bimServerApi.init(function () {
                            bimServerApi.login("admin@bimserver.com", "admin", function (data) {
                                viewer = new BIMSURFER.Viewer(bimServerApi, "viewport");
                                viewer.loadScene(function () {
                                    clickSelect = viewer.getControl("BIMSURFER.Control.ClickSelect");
                                    clickSelect.activate();
                                    clickSelect.events.register('select', nodeSelected);
                                    clickSelect.events.register('unselect', nodeUnselected);
                                }, {useCapture: true});

                                var oidsNotLoaded = [], model, ifcProject;
                                var models = {};
                                bimServerApi.getModel(196609, 196611, "ifc2x3tc1", false, function (model) {
                                    window.model = model;
                                    loadedModel = model;
                                    model.loaded = true;
                                    models[196611] = model;
                                    model.query(preLoadQuery, function (loadedObject) {

                                        if (loadedObject.isA("IfcProduct")) {
                                            oidsNotLoaded.push(loadedObject.oid);
                                            loadedObject.trans.mode = 0;
                                        }
                                    }).done(function () {
                                        var geoLoad = new GeometryLoader(bimServerApi, models, viewer);
                                        geoLoad.setLoadOids([196611], oidsNotLoaded);
                                        viewer.loadGeometry(geoLoad);
                                    });
                                });

                            });
                        });
                    });

                },
                selectObjects: function selectObjects(selectedId) {
                    var selectedRel = loadedModel.objects[selectedId];
                    var relatedObjects = selectedRel.object._rRelatedObjects;
                    relatedObjects.forEach(function (oid) {
                        if (!selectedobjects[oid]) {
                            clickSelect.pick({nodeId: oid});
                            console.log(loadedModel.objects[oid]);
                        }
                    });
                }

            }
        }();

//65539 revisionId
        viewer.init();


    }]);

