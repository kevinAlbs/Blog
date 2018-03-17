CC=gcc
CXX=g++

snippets: snippets.c
	$(CC) -o snippets snippets.c

awkward: awkward.c
	$(CC) -o awkward awkward.c

goto: goto.c
	$(CC) -o goto goto.c

unique_ptr: unique_ptr.cpp
	$(CXX) -std=c++11 -o unique_ptr unique_ptr.cpp

free_on_exit_example: free_on_exit_example.c
	$(CC) -o free_on_exit_example free_on_exit_example.c

function_call: function_call.c
	$(CC) -o function_call function_call.c

hijack: hijack.c
	$(CC) -o hijack hijack.c

hijack_2: hijack_2.c
	$(CC) -o hijack_2 hijack_2.c

one_smart_pointer: one_smart_pointer.c
	$(CC) -o one_smart_pointer one_smart_pointer.c

clean: snippets
	rm snippets awkward goto unique_ptr free_on_exit_example function_call hijack hijack_2 one_smart_pointer