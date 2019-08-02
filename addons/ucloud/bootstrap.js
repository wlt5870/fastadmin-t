//如果开启了ucloud客户端上传模式
if (typeof Config.upload.storage !== 'undefined' && Config.upload.storage === 'ucloud') {
    require(['upload', '../addons/ucloud/js/spark'], function (Upload, SparkMD5) {
        var _onFileAdded = Upload.events.onFileAdded;
        var _onUploadResponse = Upload.events.onUploadResponse;
        var _process = function (up, file) {
            (function (up, file) {
                var blob = file.getNative();
                var loadedBytes = file.loaded;
                var chunkSize = 2097152;
                var chunkBlob = blob.slice(loadedBytes, loadedBytes + chunkSize);
                var reader = new FileReader();
                reader.addEventListener('loadend', function (e) {
                    var spark = new SparkMD5.ArrayBuffer();
                    spark.append(e.target.result);
                    var md5 = spark.end();
                    Fast.api.ajax({
                        url: "/addons/ucloud/index/token",
                        data: {method: 'POST', md5: md5, name: file.name, type: file.type, size: file.size},
                    }, function (data) {
                        file.md5 = md5;
                        file.status = 1;
                        file.token = data.token;
                        file.filename = data.filename;
                        up.start();
                        return false;
                    });
                    return;
                });
                reader.readAsArrayBuffer(chunkBlob);
            })(up, file);
        };
        Upload.events.onFileAdded = function (up, files) {
            return _onFileAdded.call(this, up, files);
            _loaded = 0;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                _process(up, file, files.length);
            }
        };
        Upload.events.onBeforeUpload = function (up, file) {
            if (typeof file.md5 === 'undefined') {
                up.stop();
                _process(up, file);
            } else {
                up.settings.headers = up.settings.headers || {};
                up.settings.headers['Content-MD5'] = file.md5;
                up.settings.multipart_params.Authorization = file.token;
                up.settings.multipart_params.FileName = file.filename;
                up.settings.send_file_name = false;
            }
        };
        Upload.events.onUploadResponse = function (response, info, up, file) {
            try {
                var ret = {};
                if (info.status === 200) {
                    var url = '/' + file.filename;
                    Fast.api.ajax({
                        url: "/addons/ucloud/index/notify",
                        data: {method: 'POST', name: file.name, url: url, md5: file.md5, size: file.size, type: file.type, token: file.token}
                    }, function () {
                        return false;
                    });
                    ret.code = 1;
                    ret.data = {
                        url: url
                    };
                } else {
                    ret.code = 0;
                    ret.msg = info.response;
                }
                return _onUploadResponse.call(this, JSON.stringify(ret));

            } catch (e) {
            }
            return _onUploadResponse.call(this, response);

        };
    });
}