#include "RunTimer.hpp"



int main()
{
	RunTimer<> RT;
	RT.start_timer("aa");
	for(int i=0; i< 1000000000;i++)
	{
		i++;
	}
	RT.print_timer("aa");
	
	RT.stop_timer("aa");
	RT.sleep(1000);
	RT.print_timer("aa");
	RT.print_timer("bb");
	return 0;
}