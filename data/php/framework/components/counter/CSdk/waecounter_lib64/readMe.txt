一、使用说明
  1.需要链接以下so文件
        libnameapi.so
		libprotobuf.so
  
  
  2.使用示例：
  
     CWaeCounter counter(string("test.counter.com"));
	 long long value = counter.WaeCounterGet("hpc");