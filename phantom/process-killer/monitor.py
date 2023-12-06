#!/usr/bin/env python
import os
import sys
import time
from datetime import datetime


def main(arguments):
    loop_interval = 10
    if len(arguments) < 1:
        print('loop interval in minutes is not set, default is 30 mins')
    else:
        loop_interval = int(sys.argv[1])
        print('running with loop interval = ' + str(loop_interval))
    while True:
        clean_job()
        time.sleep(loop_interval * 60)
    pass


def clean_job():
    __location__ = os.path.realpath(os.path.join(os.getcwd(), os.path.dirname(__file__)))
    log_file = os.path.join(__location__, 'clean_job_log')
    config_file = os.path.join(__location__, 'clean_job_config')
    result_file = os.path.join(__location__, 'top_result')

    print("reading configuration from " + config_file)
    try:
        config = open(config_file)
        for line in config.readlines():
            arg = line.split()
            if len(arg) > 1:
                if arg[0] == 'percent_to_kill':
                    percent_to_kill = float(arg[1])
                if arg[0] == 'minutes_to_kill':
                    minutes_to_kill = float(arg[1])
                if arg[0] == 'path_to_kill':
                    path_to_kill = arg[1]
                if arg[0] == 'path_to_kill2':
                    path_to_kill2 = arg[1]

    except IOError:
        print('cannot read configurations from clean_job_config file, all configurations are set to default')
        percent_to_kill = 90
        minutes_to_kill = 30
        path_to_kill2 = 'chromium'
        path_to_kill = "chromedriver"

    seconds_to_kill = minutes_to_kill * 60
    print('clean job is running, will kill processes consume over ' + str(percent_to_kill) + ' after ' + str(
        minutes_to_kill) + ' minutes')
    os.system("ps -eo user,pid,etime,cmd | grep '" + path_to_kill + "\|" + path_to_kill2 + "'  > " + result_file)
    file = open(result_file)
    log_file = open(log_file, 'a')
    start = 1
    for line in file.readlines():
        a = line.split()
        if len(a) > 1:
            if start == 1:
                pid = a[1]
                user = a[0]
                time = a[2]
                if len(time) > 9:
                    pt = datetime.strptime(time, '%d-%H:%M:%S')
                elif len(time) > 7:
                    pt = datetime.strptime(time, '%H:%M:%S')
                else:
                    pt = datetime.strptime(time, '%M:%S')
                total_seconds = pt.second + pt.minute * 60 + pt.hour * 3600 + pt.day * 86400
                #                print(a)
                print(str(pid) + ' ' + user + ' ' + str(total_seconds) + ' ' + a[3])
                if user != 'root' and total_seconds > seconds_to_kill:
                    operation_string = 'kill ' + str(pid)
                    print(operation_string)
                    log_file.write(pt.ctime() + '   ' + operation_string + '   from  user: ' + user + '\n')
                    os.system('kill ' + str(pid))

    log_file.close()


if __name__ == '__main__':
    main(sys.argv[1:])
