CI_Azure
========

An extended library for users to utilize Windows Azure Table Storage in CodeIgnitor.\n
Notice that this version only supports Azure Table Storage right now, not support other types of storage.



Installation
------------

Copy this file and paste it to your CodeIgnitor folder.

For example, if the folder is named "MyAPP", then copy the file to
MyAPP/application/libraries/MY_Session.php.

Configure
---------

In /application/config/config.php, set the following parameters:


['sess_use_azure TRUE'] = "TRUE";   //Set TRUE if you want to use AZURE Storage
['sess_azure_accountname'] = "";    //The name of your storage account
['sess_azure_accountkey'] = "";     //The key of your storage account
['sess_azure_tablename'] = "";      //The table name
['sess_azure_partitionkey'] = "";   //The table partition key
