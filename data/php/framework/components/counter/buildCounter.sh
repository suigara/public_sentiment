 protoc --proto_path=./ --cpp_out=./ ./counterRequest.proto
 mv counterRequest.pb.cc counterRequest.pb.cpp
 cp counterRequest.pb.h counterRequest.pb.cpp /data/riccohuang/mcp/http_demo/so 
 cp counterRequest.pb.h counterRequest.pb.cpp /data/riccohuang/mcp/http_demo/test/


 protoc --proto_path=./ --cpp_out=./ ./counterRes.proto
 mv counterRes.pb.cc counterRes.pb.cpp
 cp counterRes.pb.h counterRes.pb.cpp /data/riccohuang/mcp/http_demo/so 
 cp counterRes.pb.h counterRes.pb.cpp /data/riccohuang/mcp/http_demo/test/
 
 cd ../protocolbuf
 php get_code_example.php counterRequest.proto counterRes.proto
 cp ./pb_proto_counterRequest.php ../counter/pb_proto_counterRequest.php
 cp ./pb_proto_counterRes.php ../counter/pb_proto_counterRes.php
