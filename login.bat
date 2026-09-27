curl.exe --url "https://www.bceexport.com/api/auth/login" ^
  -H "sec-ch-ua-platform: Windows" ^
  -H "X-CSRF-Token: b937f0409d79ef2a4b670a4b8a0a92c6ae4c2e7ae6ff7de345b926aa46e937af" ^
  -H "Referer: http://localhost:8000/" ^
  -H "sec-ch-ua: \"Google Chrome\";v=\"153\", \"Not_A Brand\";v=\"8\", \"Chromium\";v=\"153\"" ^
  -H "sec-ch-ua-mobile: ?0" ^
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36" ^
  -H "Accept: application/json" ^
  -H "Content-Type: application/json" ^
  --data-raw "{\"email\":\"admin@bceexport.com\",\"password\":\"admin123\",\"remember\":false}"