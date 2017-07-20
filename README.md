# Codisto LINQ
Codisto LINQ for Magento2

### **To install Codisto LINQ on Magento 2, perform the following:**

1.  Log in to your Magento server via **SSH** with an account that has sufficient permissions to make changes to your installation.
2.  Locate and open your **`composer.json`** file for editing in the root of the Magento 2 installation.
3.  Find the **`require`** node in the json file.  

![](https://codisto.com/images/require.png)

4.  Add the following entry to the `require` array:  

<pre>"codisto/codisto-connect": "dev-master"</pre>

![](https://codisto.com/images/composerjson.png)

5.  Run `composer install` from the appropriate user account that the Magento 2 installation is installed under.

* * *

## **Additional Notes**

It may be necessary to run the Magento 2 code compiler after the installation above is complete. eg, from the Magento root directory:

**`php bin/magento setup:di:compile`**

It also may be necessary to deploy static view files in your supported locales after installation is complete. eg, from the Magento root directory:

**`php bin/magento setup:static-content:deploy en_AU en_US`**

* * *

## **Installation service**

Alternatively we provide an installation service for a one off fee of **USD$99**. Please [**click here**](https://codisto.com/connect/) and find the 'Installation Service' button to get started.
