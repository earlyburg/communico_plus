This module integrates content from <a href="https://communico.us/" title="Communico">Communico</a> into Drupal. The module makes use of the <a href="https://api.communico.co/docs/" title="Communico api">Communico api</a> to retrieve data about Events and Reservations and display them.

The module creates two blocks, a basic feed block which is configurable and a "wall" type events display that can be filtered. Optionally there is a calendar view that can be utilized, the display of which can be toggled.

Thie module provides a service called 'communico_plus.connector' which provide 8 methods for advanced programmers to access data from Communico once Communico API access has been configured for the module.

You will need a Communico API access key and secret, which must be entered on the main configuration page to utilize the module.

For more information about Communico, please see their website: https://communico.co/

Installation:

1. Enable the module
2. Go to the admin page "/admin/config/communico_plus/config" and input the correct information for your environment and save the config.
3. Check the " Rebuild the filter block select element values" checkbox and hit save. This is    necessary to build the library locations dropdown.
