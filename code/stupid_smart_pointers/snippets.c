int main() {
// SNIPPET_BEGIN:malloc_free
char* data = (char*)malloc(100);
/* do something with data, don't need it anymore */
free(data);
// SNIPPET_END:malloc_free
}