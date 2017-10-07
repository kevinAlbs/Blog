#include "toy_driver.h"

#include <stdio.h>
#include <stdlib.h> // malloc
#include <sys/socket.h> // socket
#include <arpa/inet.h> // inet_addr
#include <errno.h> // errno
#include <unistd.h> // close
#include <string.h> // memcpy
#include <ctype.h>

struct mongo_client_t_private {
	int socket_fd;
};

mongo_client_t* mongo_connect (char* ip, int port) {
	// Create a socket to a mongod instance on localhost port 27017
  struct sockaddr_in addr = {0};
  addr.sin_family = AF_INET;
  // Now that we're taking any ip address, check for error
  int ip_as_int = inet_addr(ip);
  if (ip_as_int == -1) return NULL;
  addr.sin_addr.s_addr = ip_as_int;
  addr.sin_port = htons(port);

  int socket_fd = socket(AF_INET, SOCK_STREAM, 0);
  if (socket_fd == -1) return NULL;

  // Connect to the remote address, associating this socket
  int ret = connect (
    socket_fd, (struct sockaddr*)&addr, sizeof (struct sockaddr));

  if (ret != 0) return NULL;

  // Create our client struct and return
  mongo_client_t* client = 
    (mongo_client_t*)malloc(sizeof (mongo_client_t));
  client->socket_fd = socket_fd;

  return client;
}

void mongo_disconnect (mongo_client_t* client) {
  close (client->socket_fd);
  free (client);
}

int mongo_send_command (mongo_client_t* client,
                        char* command,
                        int command_size,
                        char* reply,
                        int reply_size,
                        int* num_recv) {

  static char op_msg_header[] = {
    0x00, 0x00, 0x00, 0x00, // total message size, including this
    0x02, 0x00, 0x00, 0x00, // requestID
    0x00, 0x00, 0x00, 0x00, // responseTo (unused for sending)
    0xDD, 0x07, 0x00, 0x00, // opCode = 2013 = 0x7DD for OP_MSG
    0x00, 0x00, 0x00, 0x00, // message flags (not needed)
    0x00                    // only data section, type 0
  };
  // Allocate enough memory for the header and command.
  int total_bytes = sizeof(op_msg_header) + command_size;
  char *op_msg = (char*)malloc(total_bytes);
  memcpy (op_msg, op_msg_header, sizeof(op_msg_header));
  memcpy (op_msg + sizeof(op_msg_header), command, command_size);

  // Set the length of the total op_msg
  int *as_int = (int*)op_msg;
  *as_int = total_bytes; // hope you're on a little-endian machine :)

  printf ("Sending\n");
  int ret = send (client->socket_fd, op_msg, total_bytes, 0);
  if (ret == -1) return 0;

  printf ("Recv'ing\n");
  *num_recv = recv (client->socket_fd, reply, reply_size, 0);
  if (*num_recv == -1) return 0;

  return 1;
}

int main() {
  mongo_client_t* client = mongo_connect("127.0.0.1", 27017);
  if (client == NULL) {
    printf ("Could not connect, error=%d\n", errno);
    return 1;
  }
  //printf ("Connected to mongod at %s port %d\n", ip, port);

  char reply_buf[512];
  char command[] = {
    // {insert: "test_coll", documents: [{x:1}]}
    0x48, 0x00, 0x00, 0x00, // total BSON obj length

    // "insert": "test_coll" key/value
    0x02, 'i','n','s','e','r','t','\0',
    0x0A, 0x00, 0x00, 0x00, // "test_coll" length
    't','e','s','t','_','c','o','l','l','\0',

    // "$db": "db"
    0x02, '$','d','b','\0',
    0x03, 0x00, 0x00, 0x00,
    'd','b','\0',

    // "documents": [{"x":1}]
    0x04, 'd','o','c','u','m','e','n','t','s','\0',
    0x16, 0x00, 0x00, 0x00, // start of {"0": {"_id": 1}} 
    0x03, '0', '\0', // key "0"
    0x0E, 0x00, 0x00, 0x00, // start of {"_id": 1}
    0x10, '_','i','d','\0', 0x01, 0x00, 0x00, 0x00,
    0x00,                   // end of {"_id": 1}
    0x00,                   // end of {"0": {"_id": 1}}
    0x00                    // end of command document
  };

  int num_recv = 0;
  if (!mongo_send_command (client, command, sizeof(command), reply_buf, sizeof(reply_buf), &num_recv)) {
    printf("Could not send, errno=%d\n", errno);
  }

  mongo_disconnect (client);
}