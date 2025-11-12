// Paginated fetch-and-save for IPTV sources
(function(window, $) {
  'use strict';

  var DEFAULT_PAGE_SIZE = 200;

  function updateProgress(page, totalPages, countThisPage) {
    var text = '已处理 第 ' + page + ' 页 / 共 ' + (totalPages === null ? '?' : totalPages) + ' 页（本页 ' + countThisPage + ' 条）';
    if (typeof $ !== 'undefined' && $('#progress').length) {
      $('#progress').text(text);
    } else {
      console.log(text);
    }
  }

  function log(msg) {
    if (typeof $ !== 'undefined' && $('#log').length) {
      $('#log').append('<div>' + msg + '</div>');
    } else {
      console.log(msg);
    }
  }

  function fetchPage(page, pageSize) {
    var url = 'manage.php?action=get_sources&page=' + encodeURIComponent(page) + '&pageSize=' + encodeURIComponent(pageSize) + '&_=' + Date.now();
    if (window.fetch) {
      return fetch(url, { credentials: 'same-origin' }).then(function(res) { return res.json(); });
    } else if (window.jQuery) {
      return new Promise(function(resolve, reject) {
        $.getJSON(url).done(resolve).fail(function(err) { reject(err); });
      });
    } else {
      return Promise.reject(new Error('No fetch or jQuery available for AJAX'));
    }
  }

  function savePage(page, pageSize, items) {
    var url = 'manage.php?action=save_sources&page=' + encodeURIComponent(page) + '&pageSize=' + encodeURIComponent(pageSize) + '&_=' + Date.now();
    var body = JSON.stringify({ items: items });

    if (window.fetch) {
      return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: body
      }).then(function(res) { return res.json(); });
    } else if (window.jQuery) {
      return new Promise(function(resolve, reject) {
        $.ajax({ url: url, method: 'POST', data: body, dataType: 'json', contentType: 'application/json', success: resolve, error: reject });
      });
    } else {
      return Promise.reject(new Error('No fetch or jQuery available for AJAX'));
    }
  }

  function fetchAndSaveAll(opts) {
    opts = opts || {};
    var pageSize = opts.pageSize || DEFAULT_PAGE_SIZE;
    var maxPages = opts.maxPages || Infinity;
    var currentPage = opts.startPage || 1;
    var totalPages = null;
    var totalItems = null;

    log('开始按页处理直播源，pageSize=' + pageSize);

    return new Promise(function(resolve, reject) {
      function next() {
        fetchPage(currentPage, pageSize)
          .then(function(resp) {
            if (!resp || typeof resp !== 'object') {
              throw new Error('后端返回格式错误');
            }
            var items = resp.items || [];
            if (totalItems === null && typeof resp.totalItems === 'number') {
              totalItems = resp.totalItems;
              totalPages = Math.ceil(totalItems / pageSize);
            }

            if (!items.length) {
              log('第 ' + currentPage + ' 页无数据，结束。');
              updateProgress(currentPage, totalPages, 0);
              return resolve({ totalItems: totalItems || 0, totalPages: totalPages || currentPage });
            }

            log('获取到第 ' + currentPage + ' 页，' + items.length + ' 条，开始保存该页...');
            return savePage(currentPage, pageSize, items).then(function(saveResp) {
              if (saveResp && saveResp.success) {
                log('保存第 ' + currentPage + ' 页成功。');
              } else {
                log('保存第 ' + currentPage + ' 页返回：' + JSON.stringify(saveResp));
              }
              updateProgress(currentPage, totalPages, items.length);

              currentPage++;
              if (currentPage > maxPages) {
                log('达到最大页数限制，停止。');
                return resolve({ totalItems: totalItems || 0, totalPages: totalPages || currentPage - 1 });
              }
              if (totalPages !== null && currentPage > totalPages) {
                log('已处理到最后一页，结束。');
                return resolve({ totalItems: totalItems || 0, totalPages: totalPages });
              }
              setTimeout(next, opts.delayMs || 50);
            });
          })
          .catch(function(err) {
            log('第 ' + currentPage + ' 页处理发生错误：' + (err && err.message ? err.message : JSON.stringify(err)));
            reject(err);
          });
      }

      next();
    });
  }

  window.iptvManage = { fetchAndSaveAll: fetchAndSaveAll, fetchPage: fetchPage, savePage: savePage };

  $(function() {
    $('#startPagedSync').on('click', function() {
      var pageSize = parseInt($('#pageSizeInput').val(), 10) || DEFAULT_PAGE_SIZE;
      $('#log').empty();
      fetchAndSaveAll({ pageSize: pageSize }).then(function(res) { log('全部完成：' + JSON.stringify(res)); }).catch(function(err) { log('处理终止，错误：' + (err && err.message ? err.message : err)); });
    });
  });

})(window, window.jQuery);
