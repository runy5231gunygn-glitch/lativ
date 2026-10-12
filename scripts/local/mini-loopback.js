// Phase 1 test copy only: route requests and bundled demo images to localhost.
// Prepend this file to a copy of the official mp_view/app.js; never to upstream.
(function () {
  var localOrigin = 'http://127.0.0.1:18080';
  var imagePrefixes = [
    'http://demo.crmeb.com/uploads/',
    'https://demo.crmeb.com/uploads/',
    'http:\\/\\/demo.crmeb.com\\/uploads\\/',
    'https:\\/\\/demo.crmeb.com\\/uploads\\/'
  ];
  function routeDemoImages(value) {
    if (typeof value === 'string') {
      imagePrefixes.forEach(function (prefix) {
        value = value.split(prefix).join(localOrigin + '/uploads/');
      });
      return value;
    }
    if (value && typeof value === 'object') {
      Object.keys(value).forEach(function (key) {
        value[key] = routeDemoImages(value[key]);
      });
    }
    return value;
  }
  var nativeRequest = wx.request;
  wx.request = function (options) {
    var localOptions = Object.assign({}, options);
    if (typeof localOptions.url === 'string' && localOptions.url.indexOf('https://demo.crmeb.com') === 0) {
      localOptions.url = localOrigin + localOptions.url.slice('https://demo.crmeb.com'.length);
      var originalSuccess = localOptions.success;
      if (typeof originalSuccess === 'function') {
        localOptions.success = function (response) {
          response.data = routeDemoImages(response.data);
          return originalSuccess.apply(this, arguments);
        };
      }
    }
    return nativeRequest.call(wx, localOptions);
  };
})();
