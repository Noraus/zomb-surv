import {defineConfig} from '@playwright/test';
export default defineConfig({testDir:'tests/browser',workers:1,timeout:45000,use:{baseURL:'http://127.0.0.1:8000',headless:true,launchOptions:{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'/usr/bin/chromium',args:['--no-sandbox']},screenshot:'only-on-failure'},reporter:'list'});
