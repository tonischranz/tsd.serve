$(function() {
  console.log("test");

  if (!Modernizr.inputtypes.date) {
    console.log("fix");

    $("input[type=date]").each(function() {
      var alt = $(this)
        .after("<input>")
        .next();

      alt.prop("readonly", $(this).prop("readonly"));

      $(this).hide();

      alt.datepicker({
        altFormat: "yy-mm-dd",
        altField: this,
        dateFormat: "dd.mm.yy"
      });
      alt.datepicker(
        "setDate",
        $.datepicker.parseDate("yy-mm-dd", $(this).val())
      );

      if ($(this).prop("readonly")) alt.datepicker("destroy");
    });
  }
});
