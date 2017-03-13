require([
    "jquery"
], function ($) { // @codingStandardsIgnoreLine Squiz.Functions.MultiLineFunctionDeclaration.SpaceBeforeOpenParen

    $("#create-account-modal").on("click", ".option", function (e) { // @codingStandardsIgnoreLine Squiz.Functions.MultiLineFunctionDeclaration.SpaceBeforeOpenParen

        $("#create-account-modal .option").removeClass("active");
        $(this).addClass("active").find("INPUT[type=radio]").attr("checked", "checked");

    });

});
