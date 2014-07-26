#include <unistd.h>
#include <ios>
#include <iostream>
#include <string>
#include <vector>
#include <chrono>
#include "RunTimer.hpp"


RunTimer<> RT;

struct CFi
{
	uint8_t b1:2;
	uint8_t b2:2;
	uint8_t b3:2;
	uint8_t b4:2;
};
union CF
{
	CFi bits;
	uint8_t uint8;
};

int main(int argc, char** argv)
{

	std::cout << "CF " << sizeof(CF) << " CFi " << sizeof(CFi) << std::endl;
	
	std::vector<uint8_t> table(256);
	table['A'] = 0;
	table['C'] = 1;
	table['G'] = 2;
	table['T'] = 3;
	
	std::string dna_char = "ACGT";
	std::string genome;
	RT.start_timer("genome time");
	for(uint32_t i=0; i< 400000000; i++)
	{
		genome += dna_char[i&3];
	}
	RT.stop_timer("genome time");
	std::cout << "Genome size: " << genome.size() << std::endl;
	
	std::vector<CF> compressed_genome_1( genome.size()/4 );
	std::vector<uint8_t> compressed_genome_2( genome.size()/4 );
	std::vector<CF> compressed_genome_3( genome.size()/4 );
	std::vector<CFi> compressed_genome_4( genome.size()/4 );
	
	//=========================================//
	RT.start_timer("old");
	for(uint32_t i=0; i < genome.size()/4; i++)
	{
		uint8_t cpint ( table[ genome[ (i<<2)+0 ] ] );
		for(uint32_t j=1; j<4; j++)
		{
			cpint = cpint << 2;
			cpint = cpint | table[ genome[ (i<<2)+j ]];
		}
		compressed_genome_2[i] = cpint;
	}
	RT.print_timer("old");
	//=========================================//
	
	
	
	//=========================================//
	RT.start_timer("bits fields version 1");
	for(uint32_t i=0; i < genome.size()/4; i++)
	{
		compressed_genome_1[i].bits.b4 = table[ genome[ (i<<2)+0 ] ];
		compressed_genome_1[i].bits.b3 = table[ genome[ (i<<2)+1 ] ];
		compressed_genome_1[i].bits.b2 = table[ genome[ (i<<2)+2 ] ];
		compressed_genome_1[i].bits.b1 = table[ genome[ (i<<2)+3 ] ];
	}
	RT.print_timer("bits fields version 1");
	//=========================================//
	
	
	//=========================================//
	RT.start_timer("bits fields version 2");
	for(uint32_t i=0; i < genome.size()/4; i++)
	{
		compressed_genome_3[i].bits = {
			table[ genome[ (i<<2)+3 ] ],
			table[ genome[ (i<<2)+2 ] ],
			table[ genome[ (i<<2)+1 ] ],
			table[ genome[ (i<<2)+0 ] ]
		};
	}
	RT.print_timer("bits fields version 2");
	//=========================================//
	
	// test is same
	for(uint32_t i=0; i < genome.size()/4; i++)
	{
		if(	compressed_genome_1[i].uint8 != compressed_genome_3[i].uint8 || compressed_genome_1[i].uint8 != compressed_genome_2[i] )
		{
			std::cout << "Error, not same" << std::endl;
			break;
		}
	}
	std::cout << "Values of three methods are the same" << std::endl;
	
	
	//=========================================//
	RT.start_timer("bits fields version 3");
	for(uint32_t i=0; i < genome.size()/4; i++)
	{
		compressed_genome_4[i] = {
			table[ genome[ (i<<2)+3 ] ],
			table[ genome[ (i<<2)+2 ] ],
			table[ genome[ (i<<2)+1 ] ],
			table[ genome[ (i<<2)+0 ] ]
		};
	}
	RT.print_timer("bits fields version 3");
	//=========================================//
	
	/* 
	Mac clang -O2
		Genome size: 400000000
		old: 7315
		bits fields version 1: 7258
		bits fields version 2: 6582
		Values of three methods are the same
		bits fields version 3: 6484
	Mac clang -O3
		Genome size: 400000000
		old: 398
		bits fields version 1: 779
		bits fields version 2: 500
		Values of three methods are the same
		bits fields version 3: 505
	Ubuntu g++-4.8 -O2
		Genome size: 400000000
		old: 1273
		bits fields version 1: 2079
		bits fields version 2: 1019
		Values of three methods are the same
		bits fields version 3: 981
	Ubuntu g++-4.8 -O3
		Genome size: 400000000
		old: 1335
		bits fields version 1: 2086
		bits fields version 2: 995
		Values of three methods are the same
		bits fields version 3: 937
	*/
	
	return 0;
}
