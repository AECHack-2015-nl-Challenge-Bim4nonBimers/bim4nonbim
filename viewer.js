var viewer = function () {
    var bimServerApi;
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

        }
    }
}(loadProject);