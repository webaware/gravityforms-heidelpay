"use strict";

(function ($) {
  /**
  * hide/reveal elements by selector
  * @param {bool} isVisible
  * @param {String} selector
  */
  function setVisibility(isVisible, selector) {
    var elements = $(selector);

    if (isVisible) {
      elements.show();
    } else {
      elements.hide();
    }
  }
  /**
  * hide/reveal custom connection fields
  */


  $("#custom_connection").change(function () {
    setVisibility(this.checked, "#heidelpay-settings-connection");
  }).trigger("change");
  /**
  * hide/reveal notifications that can be delayed until payment is received
  */

  $("#delaynotify").change(function () {
    setVisibility(this.checked, "#gaddon-setting-row-delayNotifications");
  }).trigger("change");
  /**
  * record changes in delay notifications individual checkboxes
  */

  $("input.heidelpay-notification-checkbox").on("click change", function () {
    var notifications = {};
    $('.heidelpay-notification-checkbox').each(function () {
      notifications[this.value] = this.checked ? 1 : 0;
    });
    $('#delayNotifications').val($.toJSON(notifications));
  });
  /**
  * hide/reveal credit cards
  */

  $("#heidelpay-enabled-methods-CC").change(function () {
    setVisibility(this.checked, "#heidelpay-enabled-methods-creditcards");
  }).trigger("change");
  /**
  * record changes in delay notifications individual checkboxes
  */

  $("input.heidelpay-enabled-methods-checkbox").on("click change", function () {
    var methods = {};
    $('.heidelpay-enabled-methods-checkbox').each(function () {
      methods[this.value] = this.checked ? 1 : 0;
    });
    $('#enabledMethods').val($.toJSON(methods));
  });
})(jQuery);
