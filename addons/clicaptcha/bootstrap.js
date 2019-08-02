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
