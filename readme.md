This module is working from prestashop 1.7.8.7 to latest version.
 
(Optional Steps)

- You have to go to your local prestashop folder and find config/settings.inc and add this line of code: define('PS_VERSION', '1.X.X.X'); inside the settings.inc file (you can open it with any IDE like VS Code or Notepad++) and save the changes.

- The version number 1.X.X.X is your prestashop version number that you need to define inside the config file. For example if you run prestashop 1.7.8.7 the line of code should be: define('PS_VERSION', '1.7.8.7');