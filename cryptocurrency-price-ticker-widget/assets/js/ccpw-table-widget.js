jQuery(document).ready(function ($) {
  var table_id = "";

  function ccpw_safe_url(url) {
    try {
      var parsed = new URL(url, window.location.origin);
      if (parsed.protocol !== "https:" && parsed.protocol !== "http:")
        return null;
      return parsed.href;
    } catch (e) {
      return null;
    }
  }
  function ccpw_text_span(cls, value) {
    return $("<span>").addClass(cls).text(value);
  }

  $.fn.ccpwDatatable = function () {
    table_id = $(this).attr("id");
    var $ccpw_table = $(this);
    var columns = [];
    var rtype = $ccpw_table.data("rtype");
    var coinList = $ccpw_table.data("coin-list");

    var fiatSymbol = $ccpw_table.data("currency-symbol");
    var fiatCurrencyRate = $ccpw_table.data("currency-rate");
    var pagination = $ccpw_table.data("pagination");
    var fiatCurrency = $ccpw_table.data("currency-type");
    var requiredCurrencies = $ccpw_table.data("required-currencies");
    var prevtext = $ccpw_table.data("prev-coins");
    var nexttext = $ccpw_table.data("next-coins");
    var zeroRecords = $ccpw_table.data("zero-records");
    var currencyLink = $ccpw_table.data("currency-slug");
    var dynamicLink = $ccpw_table.data("dynamic-link");
    var loadingLbl = $ccpw_table.data("loadinglbl");
    var numberFormat = $ccpw_table.data("number-formating");
    $ccpw_table.find("thead th").each(function (index) {
      var thisTH = $(this);
      var index = thisTH.data("index");
      var classes = thisTH.data("classes");

      columns.push({
        data: index,
        name: index,
        render: function (data, type, row, meta) {
          if (meta.settings.json === undefined) {
            return data;
          }
          switch (index) {
            case "rank":
              return data;
              break;
            case "name":
              var $wrap = $("<div>").addClass(classes);
              var $logoSpan = $("<span>").addClass("ccpw_coin_logo");
              if (typeof row.logo === "string" && row.logo.trim() !== "") {
                // Parse the HTML string in a detached element — never inserted into the live DOM
                var $parsed = $("<div>").html(row.logo).find("img").first();
                var rawSrc = $parsed.attr("src") || "";
                var rawAlt = $parsed.attr("alt") || row.name;
                var safeSrc = ccpw_safe_url(rawSrc); // rejects javascript:, data:, etc.
                if (safeSrc) {
                  $("<img>")
                    .attr("src", safeSrc)
                    .attr("alt", rawAlt)
                    .attr("width", 32)
                    .appendTo($logoSpan);
                }
              }
              var $logo = $logoSpan;
              var $symbol = ccpw_text_span(
                "ccpw_coin_symbol",
                "(" + row.symbol + ")",
              );
              var $name = ccpw_text_span(
                "ccpw_coin_name ccpw-desktop",
                row.name,
              );

              if (typeof dynamicLink !== "undefined" && dynamicLink !== "") {
                var safeHref = ccpw_safe_url(
                  currencyLink + "/" + row.symbol + "/" + row.id,
                );
                if (safeHref) {
                  var $a = $("<a>")
                    .addClass("ccpw_links")
                    .attr("href", safeHref)
                    .attr("title", row.name)
                    .append($logo, $symbol, $("<br>"), $name);
                  $wrap.append($a);
                }
              } else {
                $wrap.append($logo, $symbol, $("<br>"), $name);
              }
              return $("<div>").append($wrap).html();
            case "price":
              if (typeof data !== "undefined" && data != null) {
                var formatedVal = ccpw_numeral_formating(data);
                return $("<div>")
                  .addClass(classes)
                  .attr("data-val", row.price)
                  .append(
                    $("<span>")
                      .addClass("ccpw-formatted-price")
                      .text(fiatSymbol + formatedVal),
                  )
                  .prop("outerHTML");
              } else {
                return $("<div>").addClass(classes).text("?").prop("outerHTML");
              }
              break;
            case "change_percentage_24h":
              if (typeof data !== "undefined" && data != null) {
                if (typeof Math.sign === "undefined") {
                  Math.sign = function (x) {
                    return x > 0 ? 1 : x < 0 ? -1 : x;
                  };
                }
                var isDown = Math.sign(data) === -1;
                var changesCls = isDown ? "down" : "up";
                var wrpCls = isDown ? "ccpw-down" : "ccpw-up";
                var $icon = $("<i>")
                  .addClass("dashicons dashicons-arrow-" + changesCls)
                  .attr("aria-hidden", "true");
                var $span = $("<span>")
                  .addClass("changes " + changesCls)
                  .append($icon)
                  .append(document.createTextNode(data + "%"));
                return $("<div>")
                  .addClass(classes + " " + wrpCls)
                  .append($span)
                  .prop("outerHTML");
              } else {
                return $("<div>").addClass(classes).text("?").prop("outerHTML");
              }
              break;
            case "market_cap":
              if (typeof data !== "undefined" && data != null) {
                var formatedVal = ccpw_numeral_formating(data);
                if (numberFormat) {
                  var formatedVal = numeral(data)
                    .format("(0.00 a)")
                    .toUpperCase();
                }
                return $("<div>")
                  .addClass(classes)
                  .attr("data-val", row.market_cap)
                  .append(
                    $("<span>")
                      .addClass("ccpw-formatted-market-cap")
                      .text(fiatSymbol + formatedVal),
                  )
                  .prop("outerHTML");
              } else {
                return (html = '<div class="' + classes + ">?</div>");
              }
              break;
            case "total_volume":
              if (
                typeof data !== "undefined" &&
                data != null &&
                data != "0.00"
              ) {
           
                var formatedVal = ccpw_numeral_formating(data);
                if (numberFormat) {
                  var formatedVal = numeral(data)
                    .format("(0.00 a)")
                    .toUpperCase();
                }
                return $("<div>")
                  .addClass(classes)
                  .attr("data-val", row.total_volume)
                  .append(
                    $("<span>")
                      .addClass("ccpw-formatted-total-volume")
                      .text(fiatSymbol + formatedVal),
                  )
                  .prop("outerHTML");
              } else {
                return (html = '<div class="' + classes + '">?</div>');
              }
              break;
            case "supply":
              if (
                typeof data !== "undefined" &&
                data != null &&
                row.supply != "N/A"
              ) {
                var formatedVal = ccpw_numeral_formating(data);
                if (numberFormat) {
                  var formatedVal = numeral(data)
                    .format("(0.00 a)")
                    .toUpperCase();
                }
                return $("<div>")
                  .addClass(classes)
                  .attr("data-val", row.supply)
                  .append(
                    $("<span>")
                      .addClass("ccpw-formatted-supply")
                      .text(formatedVal + " " + row.symbol),
                  )
                  .prop("outerHTML");
              } else {
                return (html = '<div class="' + classes + '">N/A</div>');
              }
              break;

            default:
              return data;
          }
        },
        createdCell: function (td, cellData, rowData, row, col) {
          $(td).attr("data-sort", cellData);
        },
      });
    });

    $ccpw_table.DataTable({
      deferRender: true,
      serverSide: true,
      ajax: {
        url: ccpw_js_objects.ajax_url,
        type: "POST",
        dataType: "JSON",
        data: function (d) {
          ((d.action = "ccpw_get_coins_list"),
            (d.nonce = ccpw_js_objects.wp_nonce),
            (d.currency = fiatCurrency),
            (d.currencyRate = fiatCurrencyRate),
            (d.requiredCurrencies = requiredCurrencies),
            (d.rtype = rtype),
            (d.coinslist = coinList));
          // etc
        },

        error: function (xhr, error, thrown) {
          alert("Something wrong with Server");
        },
      },
      ordering: false,
      searching: false,
      pageLength: pagination,
      columns: columns,
      responsive: true,
      lengthChange: false,
      pagingType: "simple",
      processing: true,
      dom: '<"top"iflp<"clear">>rt<"bottom"iflp<"clear">>',
      language: {
        processing: loadingLbl,
        loadingRecords: loadingLbl,
        paginate: {
          next: nexttext,
          previous: prevtext,
        },
      },
      zeroRecords: zeroRecords,
      emptyTable: zeroRecords,
      renderer: {
        header: "bootstrap",
      },
      initComplete: function (settings, json) {
        if (json.error == "nonce_failed") {
          $(this)
            .find(".dataTables_empty")
            .html(
              '<span style="color:red">Attention: Please exclude this page from your cache plugin, as it is currently causing a nonce failure.<br> For detailed instructions on how to implement the exclusion, kindly follow this link: <a href="https://cryptocurrencyplugins.com/docs/coins-marketcap/nonce-validation-failed" target="_balnk">Exclusion Guide..</a></span>',
            );
        }
      },
      drawCallback: function (settings) {
        $ccpw_table.tableHeadFixer({
          // fix table header
          head: true,
          // fix table footer
          foot: false,
          left: 2,
          right: false,
          "z-index": 1,
        });
      },
    });
  };

  $(".ccpw_table_widget").each(function () {
    $.fn.dataTable.ext.errMode = "none";
    $(this).ccpwDatatable();
  });

  if (table_id) {
    new Tablesort(document.getElementById(table_id), {
      descending: true,
    });
  }

  function ccpw_numeral_formating(data) {
    if (data >= 25 || data <= -1) {
      var formatedVal = numeral(data).format("0,0.00");
    } else if (data >= 0.5 && data < 25) {
      var formatedVal = numeral(data).format("0,0.000");
    } else if (data >= 0.01 && data < 0.5) {
      var formatedVal = numeral(data).format("0,0.0000");
    } else if (data >= 0.0001 && data < 0.01) {
      var formatedVal = numeral(data).format("0,0.00000");
    } else {
      var formatedVal = numeral(data).format("0,0.00000000");
    }
    return formatedVal;
  }
});
