void* free_on_exit(void* ptr) {
    return ptr;
}

// SNIPPET_BEGIN:free_on_exit_example
void f() {
    char* resource_1 = free_on_exit(get_resource());
    if (resource_1 == NULL) return;

    char* resource_2 = free_on_exit(get_resource());
    if (resource_2 == NULL) return;

    char* resource_3 = free_on_exit(get_resource());
    if (resource_3 == NULL) return;
}
// SNIPPET_END:free_on_exit_example

int main() {
    f();
}