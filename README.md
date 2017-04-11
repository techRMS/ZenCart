Compatability
=========================

Tested with version 1.5.5e of Zencart.


Installation Instructions
=========================

**Step 1:**

Make sure that all files inside the httpdocs folder are inserted into the root directory of where Zen Cart is installed (including cardstream_callback.php in the main folder). 

**Step 2:**

Some installations will not require you to run the SQL files provided. However, strict database privileges on a server (specifically CREATE, DELETE and ALTER) will prevent the a full installation of this module. If this is the case, you will need to manually run the SQL into your site's database with CREATE, and ALTER priveledges; making sure to allow DELETE access for cardstream_temp_carts normal operation which will help keep the table clean and save space. Ensure that you enter your correct table name (my_table_name in the example below) in orders.sql 


```
ALTER TABLE my_table_name ADD COLUMN `cardstream_xref` VARCHAR(128) NULL, ADD COLUMN `cardstream_transactionUnique` VARCHAR(128) NULL, ADD COLUMN 
`cardstream_amount_received` FLOAT NOT NULL DEFAULT '0.0', ADD COLUMN `cardstream_authorisationCode` VARCHAR(128) NULL, ADD COLUMN `cardstream_responseMessage` 
TEXT NULL, ADD COLUMN `cardstream_lastAction` VARCHAR(32) NULL
```

**Step 3:**

Mouseover the Modules link in the top menu and click Payment. Next, click the circle for CardStream Form and finally click install on the right side of the screen. 

**Step 4:**

Follow the instructions that appear, and enter all the necessary details, such as the Merchant ID and signature. Click update, and Cardstream is now integrated with your shopping cart. 

If you would like to make edits to the Cardstream Integration such as updating the Merchant ID, hover over the modules drop down menu and click payments. Click on Cardstream Integration in the list that appears, and click on edit on the right hand side. 


