```python
markdown_content = """# Setup Guide: Connecting PHP MCP Server to Google Antigravity

This guide provides step-by-step instructions to connect a PHP-based Model Context Protocol (MCP) server to Google Antigravity as of June 2026.

## Prerequisites
- **PHP 8.0+** installed locally.
- **Composer** for package management.
- **Google Antigravity** application (functioning as the MCP Client).

---

## Step 1: Set Up Your PHP MCP Server Project

If you do not have an existing PHP MCP project, initialize one and install the SDK:


```

```text
File created successfully: php_mcp_antigravity_setup.md

```bash
mkdir php-mcp-server
cd php-mcp-server
composer require mcp/sdk

```

Create a file named `server.php`. This file will serve as the entry point for the MCP server, defining the tools, resources, and prompts that your AI Agent can interact with.

---

## Step 2: Access the Google Antigravity Configuration

Google Antigravity discovers available servers through an `mcp_config.json` file. You can access and modify this file in two ways:

### Method A: Through the Antigravity UI

1. Open **Google Antigravity**.
2. Navigate to the **Agent Panel** on the right side of the editor.
3. Click the **`...` (Three dots)** menu in the top right corner.
4. Select **MCP Servers** to open the server dashboard.
5. Click **Manage MCP Servers** and choose **View raw config** to open the underlying JSON file.

### Method B: Directly Editing the File Path

Depending on your preferred scope, you can locate the file directly on your system:

* **Global Scope (All Projects):** `~/.gemini/config/mcp_config.json`
* **Workspace Scope (Current Project Only):** `.agents/mcp_config.json`

---

## Step 3: Register the PHP Server in `mcp_config.json`

Add your custom PHP server inside the `mcpServers` block. You must use **absolute paths** for both the `php` executable and your script, because Antigravity executes tasks within an isolated shell environment.

Edit your `mcp_config.json` to reflect the following structure:

```json
{
  "mcpServers": {
    "my-php-mcp-server": {
      "command": "/usr/local/bin/php",
      "args": [
        "/absolute/path/to/your/php-mcp-server/server.php"
      ],
      "env": {
        "PATH": "/usr/local/bin:/usr/bin:/bin"
      }
    }
  }
}

```

> 💡 **Tip:** To find the absolute path of your PHP executable, run `which php` in your local terminal and replace `/usr/local/bin/php` with your actual path.

---

## Step 4: Restart and Test the Connection

1. Save your changes to `mcp_config.json`.
2. Fully restart **Google Antigravity** (or click the **Refresh** icon in the Installed MCP Servers panel).
3. Open the Agent Chat window and ask a question that triggers one of your custom PHP tools.
4. The Gemini agent inside Antigravity will automatically communicate with your PHP script via Stdio (Standard Input/Output) to execute the required backend actions.
"""

file_name = "php_mcp_antigravity_setup.md"
with open(file_name, "w", encoding="utf-8") as f:
f.write(markdown_content)

print(f"File created successfully: {file_name}")

```
ระบบได้สร้างไฟล์ Markdown (.md) สำหรับขั้นตอนการติดตั้งในเวอร์ชันภาษาอังกฤษ (English version) ตามที่คุณต้องการเรียบร้อยแล้วครับ

[file-tag: code-generated-file-0-1782137046698502775]

**รายละเอียดภายในไฟล์จะประกอบด้วย:**
1. **Prerequisites:** สิ่งที่ต้องเตรียม (PHP 8+, Composer, Google Antigravity)
2. **Step 1:** วิธีเตรียมโปรเจกต์ PHP และการติดตั้ง SDK ผ่าน Composer
3. **Step 2:** วิธีการเข้าถึงและค้นหาตำแหน่งไฟล์คอนฟิก `mcp_config.json` ทั้งผ่าน UI ของ Antigravity และ Direct Path
4. **Step 3:** ตัวอย่างการเขียน JSON คอนฟิกแบบละเอียด (เน้นย้ำการใช้ Absolute Path เพื่อป้องกัน Error ใน Environment ของ Antigravity)
5. **Step 4:** ขั้นตอนการรีสตาร์ตระบบและทดสอบเรียกใช้งาน Tool ผ่าน Agent AI หลังบ้าน

คุณสามารถดาวน์โหลดไฟล์ `.md` นี้ไปเปิดใช้งาน ดูโค้ด หรือเก็บบันทึกไว้ในคลังความรู้ (Documentation) ของโปรเจกต์ได้เลยครับ หากต้องการให้ปรับแต่งคำสั่งหรือเพิ่มตัวอย่าง Logic ส่วนไหนเพิ่มเติม แจ้งได้เลยนะครับ!

```