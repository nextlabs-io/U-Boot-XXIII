from selenium import webdriver
import time
import json
from fake_useragent import UserAgent



for i in range(1000):
    ua = UserAgent()
    userAgent = ua.random
    print userAgent

