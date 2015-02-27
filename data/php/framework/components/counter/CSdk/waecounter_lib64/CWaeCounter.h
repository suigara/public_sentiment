#ifndef CWAECOUNTER_H
#define CWAECOUNTER_H
#include<string>

using namespace std;

class TcpCltSocket;

class CWaeCounter
{
  public:
  CWaeCounter(string dnsName);
  long long WaeCounterGet(string key);
  long long WaeCounterIncr(string key);
  long long WaeCounterDecr(string key);
  long long WaeCounterExpire(string key,int expireSeconds);
     string WaeCounterGetErrMsg(); 
  ~CWaeCounter();

  private:
   bool connect(string dnsName);

  private:
   TcpCltSocket*  m_pSocket;
   string        m_dnsName;
   int           m_iPort;
   string        m_ip;
   string        m_errMsg;
   bool          m_bConnected;
};










#endif
