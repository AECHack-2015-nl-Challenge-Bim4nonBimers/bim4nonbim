var viewer = function () {
    var bimServerApi, viewer;
    return {
        init: function init(revisionId) {
                var address = 'address';
                var notifier = new Notifier();
                bimServerApi = new BimServerApi(address, notifier);
                bimServerApi.init(function () {
                    bimServerApi.login("user", "pass", true, function (data) {
                        start(bimServerApi, revisionId);
                    });
                });

        },
        start: function start(revisionId){
            viewer = new BIMSURFER.Viewer(bimServerApi, "viewport");
            viewer.drawCanvas = function () {
                var canvas = viewer.drawCanvas.bind(viewer);
                canvas[0].getContext("experimental-webgl", {preserveDrawingBuffer: true});
            }
        }
    }
}(revisionId);