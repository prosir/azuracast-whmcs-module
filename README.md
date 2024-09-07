# WHMCS AzuraCast Provisioning Module

## Overview

The **WHMCS AzuraCast Provisioning Module** allows you to automatically create, suspend, and terminate AzuraCast radio stations through WHMCS. It includes a one-click login button that allows clients to access their AzuraCast dashboard without entering credentials manually.

### Features
- Automated Account Management: Automatically provision, suspend, and terminate radio stations.
- Client Control: Clients can start, stop, or restart their stations from WHMCS.
- One-Click Login: Provides clients with a button to directly log into their AzuraCast panel.

## Installation

1. Download and extract the module files.
2. Upload the files to `modules/servers/azuracast/` in your WHMCS directory.
3. Ensure that WHMCS has read access to these files.

## Configuration

### Step 1: Setting Up in WHMCS
1. Log in to WHMCS Admin.
2. Navigate to **Setup > Products/Services > Products/Services**.
3. Create a New Product and under **Module Settings**, select **AzuraCast Module**.
4. Configure the product options like:
   - **Storage Limit** (in MB)
   - **Maximum Listeners**
   - **Bitrate** (in kbps)

### Step 2: Configuring AzuraCast API
1. Get your API Key from the AzuraCast dashboard:
   - Go to **Administration > API Keys**.
   - Create a new API key with the required permissions.
2. Edit the `azuracast.php` file:
   - Replace the `your_api_key` and `your-azuracast-url.com` with your actual API key and URL.

Example:
```php
$apiUrl = "https://your-azuracast-url.com/api/stations";
$apiKey = "your_actual_api_key";
```

## Features Walkthrough

### One-Click Login Button
Once the station is provisioned, clients will see a **"Login to AzuraCast"** button in their WHMCS client area. Clicking this button will log them into their AzuraCast panel without needing to enter credentials.

## Troubleshooting

If you encounter any issues, please contact support or check the WHMCS module logs under **Utilities > Logs > Module Log**.

