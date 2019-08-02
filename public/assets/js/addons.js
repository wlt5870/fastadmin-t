define([], function () {
    require.config({
    paths: {
        'clicaptcha': '../addons/clicaptcha/js/clicaptcha'
    },
    shim: {
        'clicaptcha': {
            deps: [
                'jquery',
                'css!../addons/clicaptcha/css/clicaptcha.css'
            ],
            exports: '$.fn.clicaptcha'
        }
    }
});

require(['clicaptcha'], function () {
    window.clicaptcha = function (captcha) {
        if (captcha.size() > 0) {
            var form = captcha.closest("form");
            if (captcha.parent().is("div.form-group") || captcha.parent().is("div.input-group")) {
                captcha.parent().addClass("hidden");
            }
            captcha.attr("data-rule", "required");
            // 验证失败时进行操作
            captcha.on('invalid.field', function (e, result, me) {
                //必须删除errors对象中的数据，否则会出现Layer的Tip
                delete me.errors['captcha'];
                if (result.key === 'captcha') {
                    captcha.clicaptcha({
                        src: '/addons/clicaptcha/index/start',
                        success_tip: '验证成功！',
                        error_tip: '未点中正确区域，请重试！',
                        callback: function (captchainfo) {
                            form.trigger("submit");
                            return false;
                        }
                    });
                }
            });
            // 监听表单错误事件
            form.on("error.form", function (e, data) {
                captcha.val('');
            });
        }
    };
    clicaptcha($("input[name=captcha]"));
});

if ($('.cropper', $('form[role="form"]')).length > 0) {
    var allowAttr = [
        'aspectRatio', 'autoCropArea', 'cropBoxMovable', 'cropBoxResizable', 'minCropBoxWidth', 'minCropBoxHeight', 'minContainerWidth', 'minContainerHeight',
        'minCanvasHeight', 'minCanvasWidth', 'croppedWidth', 'croppedHeight', 'croppedMinWidth', 'croppedMinHeight', 'croppedMaxWidth', 'croppedMaxHeight', 'fillColor'
    ];
    String.prototype.toLineCase = function () {
        return this.replace(/[A-Z]/g, function (match) {
            return "-" + match.toLowerCase();
        });
    };

    var btnAttr = [];
    $.each(allowAttr, function (i, j) {
        btnAttr.push('data-' + j.toLineCase() + '="<%=data.' + j + '%>"');
    });
    var btn = '<button class="btn btn-success btn-cropper btn-xs" data-input-id="<%=data.inputId%>" ' + btnAttr.join(" ") + ' style="position:absolute;top:10px;right:15px;">裁剪</button>';
    require(['upload'], function (Upload) {
        //图片裁剪
        $(document).on('click', '.btn-cropper', function () {
            var image = $(this).closest("li").find('.thumbnail').data('url');
            var input = $("#" + $(this).data("input-id"));
            var url = image;
            var data = $(this).data();
            var params = [];
            $.each(allowAttr, function (i, j) {
                if (typeof data[j] !== 'undefined' && data[j] !== '') {
                    params.push(j + '=' + data[j]);
                }
            });
            (parent ? parent : window).Fast.api.open('/addons/cropper/index/cropper?url=' + image + (params.length > 0 ? '&' + params.join('&') : ''), '裁剪', {
                callback: function (data) {
                    if (typeof data !== 'undefined') {
                        var arr = data.dataURI.split(','), mime = arr[0].match(/:(.*?);/)[1],
                            bstr = atob(arr[1]), n = bstr.length, u8arr = new Uint8Array(n);
                        while (n--) {
                            u8arr[n] = bstr.charCodeAt(n);
                        }
                        var urlArr = url.split('.');
                        var suffix = 'png';
                        url = urlArr.join('');
                        var filename = url.substr(url.lastIndexOf('/') + 1);
                        var exp = new RegExp("\\." + suffix + "$", "i");
                        filename = exp.test(filename) ? filename : filename + "." + suffix;
                        var file = new File([u8arr], filename, {type: mime});
                        Upload.api.send(file, function (data) {
                            input.val(input.val().replace(image, data.url)).trigger("change");
                        }, function (data) {
                        });
                    }
                },
                area: ["880px", "520px"],
            });
            return false;
        });

        var insertBtn = function () {
            return arguments[0].replace(arguments[2], btn + arguments[2]);
        };
        Upload.config.previewtpl = Upload.config.previewtpl.replace(/<li(.*?)>(.*?)<\/li>/, insertBtn);
        $(".cropper").each(function () {
            var preview = $("#" + $(this).data("preview-id"));
            if (preview.size() > 0 && preview.data("template")) {
                var tpl = $("#" + preview.data("template"));
                tpl.text(tpl.text().replace(/<li(.*?)>(.*?)<\/li>/, insertBtn));
            }
        });
    });
}
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
});