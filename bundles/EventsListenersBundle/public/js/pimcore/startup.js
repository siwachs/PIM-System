pimcore.registerNS("pimcore.plugin.EventsListenersBundle");

pimcore.plugin.EventsListenersBundle = Class.create({

    initialize: function () {
        document.addEventListener(pimcore.events.pimcoreReady, this.pimcoreReady.bind(this));
    },

    pimcoreReady: function (e) {
        // alert("EventsListenersBundle ready!");
    }
});

var EventsListenersBundlePlugin = new pimcore.plugin.EventsListenersBundle();
