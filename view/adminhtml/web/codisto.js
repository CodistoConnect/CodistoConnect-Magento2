require([
    "jquery"
], function ($) {

    $("#create-account-modal").on("click", ".option", function (e) {

        $("#create-account-modal .option").removeClass("active");
        $(this).addClass("active").find("INPUT[type=radio]").attr("checked", "checked");

    });

});
