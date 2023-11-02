pimcore.registerNS("pimcore.plugin.FileHandlingBundle");

pimcore.plugin.FileHandlingBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("FileHandlingBundle ready!");
    }
});

var FileHandlingBundlePlugin = new pimcore.plugin.FileHandlingBundle();
