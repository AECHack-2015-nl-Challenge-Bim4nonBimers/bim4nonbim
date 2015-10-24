var viewer = function () {
    var bimServerApi, viewer;
    var preLoadQuery = {
        defines: {
            Representation: {
                field: "Representation"
            },
            ContainsElementsDefine: {
                field: "ContainsElements",
                include: {
                    field: "RelatedElements",
                    include: ["IsDecomposedByDefine", "ContainsElementsDefine", "Representation"]
                }
            },
            IsDecomposedByDefine: {
                field: "IsDecomposedBy",
                include: {
                    field: "RelatedObjects",
                    include: ["IsDecomposedByDefine", "ContainsElementsDefine", "Representation"]
                }
            }
        },
        queries: [
            {
                type: "IfcProject",
                include: ["IsDecomposedByDefine", "ContainsElementsDefine"]
            },
            {
                type: "IfcProduct",
                includeAllSubtypes: true
            }
        ]
    };

    function nodeSelected() {
        //todo: when element is selected
    }

    function nodeUnselected() {
//todo: when element is unselected
    }

    return {
        init: function init(revisionId) {
            var address = 'http://10.30.22.250:8082/';
            var notifier = new Notifier();
            bimServerApi = new BimServerApi(address, notifier);
            bimServerApi.init(function () {
                bimServerApi.login("admin@bimserver.com ", "admin", true, function (data) {
                    start(bimServerApi, revisionId);
                });
            });

        },
        start: function start(revisionId) {
            viewer = new BIMSURFER.Viewer(bimServerApi, "viewport");
            viewer.loadScene(function () {
                var clickSelect = viewer.getControl("BIMSURFER.Control.ClickSelect");
                clickSelect.activate();
                clickSelect.events.register('select', nodeSelected);
                clickSelect.events.register('unselect', nodeUnselected);
            }, {useCapture: true});

            var oidsNotLoaded = [], model, ifcProject;
            model = new Model(bimServerAPI, projectId, revisionId, 'ifc2x3tc1');
            model.loaded = true;
            model.query(preLoadQuery, function (loadedObject) {
                if (loadedObject.getType() == 'IfcProject') {
                    ifcProject = loadedObject;
                    loadedObject.trans.mode = 0;
                }
                if (loadedObject.isA("IfcProduct")) {
                    oidsNotLoaded.push(loadedObject.oid);
                    loadedObject.trans.mode = 0;
                }
            }).done(function () {
                var geoLoad = new GeometryLoader(bimServerApi, model, viewer);
                geoLoad.setLoadOids([revisionId], oidsNotLoaded);
                viewer.loadGeometry(geoLoad);
            });
        }

    }
}(revisionId);

//65539 revisionId