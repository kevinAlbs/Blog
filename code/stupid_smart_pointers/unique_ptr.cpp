#include <memory>
char *get_resource () {
   return NULL;
}

// SNIPPET_BEGIN:unique
void f () {
   auto resource_1 = std::unique_ptr<char> (get_resource ());
   if (resource_1.get () == nullptr) return;
   auto resource_2 = std::unique_ptr<char> (get_resource ());
   if (resource_2.get () == nullptr) return;
   auto resource_3 = std::unique_ptr<char> (get_resource ());
   if (resource_3.get () == nullptr) return;
   /* ... */
}
// SNIPPET_END:unique

int main () {
   f ();
}