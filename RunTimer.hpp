#include <iostream>
#include <chrono>
#include <string>
#include <tuple>
#include <thread>
#include <map>

template<class CLOCK_TYPE>
struct Timer
	:public std::tuple<typename CLOCK_TYPE::time_point, typename CLOCK_TYPE::time_point>
{
	typedef typename CLOCK_TYPE::time_point TIME_POINT_TYPE;
	Timer()
		:std::tuple<TIME_POINT_TYPE, TIME_POINT_TYPE>(CLOCK_TYPE::now(), CLOCK_TYPE::now())
	{}
};

template<class CLOCK_TYPE = std::chrono::steady_clock, class TIME_UNIT_TYPE = std::chrono::milliseconds>
class RunTimer
{
public:
	typedef typename CLOCK_TYPE::time_point TIME_POINT_TYPE;
	
private:
	//std::map<std::string, std::tuple<TIME_POINT_TYPE, TIME_POINT_TYPE> > timer_record_;
	std::map<std::string, Timer<CLOCK_TYPE> > timer_record_;
	
public:
	RunTimer()
	{
		//Timer<CLOCK_TYPE> aaa;
	}
	void start_timer(const std::string &timer_name)
	{
		std::get<0>(timer_record_[timer_name]) = CLOCK_TYPE::now();
	}
	void stop_timer(const std::string &timer_name)
	{
		std::get<1>(timer_record_[timer_name]) = CLOCK_TYPE::now();
	}
	void print_timer(const std::string &timer_name)
	{
		auto now_time = CLOCK_TYPE::now();
		std::cout << std::chrono::duration_cast<TIME_UNIT_TYPE> (now_time - std::get<0>(timer_record_[timer_name])).count() << "\n";
	}
	void sleep(int duration)
	{
		TIME_UNIT_TYPE dura( duration );
		std::this_thread::sleep_for( dura );
	}
	//std::chrono::duration_cast<std::chrono::milliseconds>
	//std::clock_t c_start = std::clock();
	//std::chrono::duration_cast<std::chrono::milliseconds>(t_end - t_start).count()
	//std::chrono::time_point
	
	
};